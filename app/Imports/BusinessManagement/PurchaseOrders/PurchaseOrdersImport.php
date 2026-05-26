<?php

namespace App\Imports\BusinessManagement\PurchaseOrders;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports PurchaseOrders header-only from .xlsx/.csv.
 *
 * Las lineas (items) NO se importan — se editan despues desde la UI. Esto
 * mantiene el importer simple (header + dedup por reference) sin tener que
 * resolver SKUs a productos del catalogo.
 *
 * Columns:
 *   - reference         (optional, max 30, unique per tenant; autogen si vacio)
 *   - supplier_name     (required, busca Company por nombre case-insensitive)
 *   - warehouse_code    (required, busca Warehouse por code o name)
 *   - status            (required, en PurchaseOrder::STATUSES)
 *   - order_date        (required, parseable date)
 *   - expected_delivery_date (optional)
 *   - currency_code     (optional, 3 chars)
 *   - payment_terms_days (optional, int >= 0)
 *   - delivery_type     (optional, pickup|courier|freight)
 *   - notes             (optional, max 2000)
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * Dedup por `reference` (unique-per-tenant). Si en el archivo aparece dos
 * veces el mismo reference, el segundo se marca como error.
 */
class PurchaseOrdersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    public array $errors  = [];
    public array $preview = [];

    /** Cache supplier_name → company_id, warehouse_code → warehouse_id. */
    protected array $supplierCache  = [];
    protected array $warehouseCache = [];

    protected ?int $tenantId;
    protected int $maxRecords;
    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user           = Auth::user();
        $this->tenantId = $user?->tenant_id;

        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        $this->currentCount = $this->tenantId !== null
            ? PurchaseOrder::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : PurchaseOrder::withoutGlobalScopes()->count();

        // Precarga catalogos por tenant.
        Company::query()
            ->when($this->tenantId !== null, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->whereIn('company_type', ['supplier', 'both', 'partner'])
            ->get(['id', 'name'])
            ->each(function ($c) {
                $this->supplierCache[$this->normalizeKey($c->name)] = $c->id;
            });

        Warehouse::query()
            ->when($this->tenantId !== null, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->where('is_active', true)
            ->get(['id', 'code', 'name'])
            ->each(function ($w) {
                if ($w->code) $this->warehouseCache[mb_strtoupper(trim($w->code))] = $w->id;
                $this->warehouseCache[$this->normalizeKey($w->name)] = $w->id;
            });
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileByRef = [];
            $newRecordsCount = 0;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $reference   = trim((string) ($row['reference'] ?? ''));
                $supplierKey = $this->normalizeKey((string) ($row['supplier_name'] ?? ''));
                $whKeyRaw    = trim((string) ($row['warehouse_code'] ?? ''));
                $status      = trim((string) ($row['status'] ?? 'draft'));
                $orderDate   = trim((string) ($row['order_date'] ?? ''));
                $etaDate     = trim((string) ($row['expected_delivery_date'] ?? ''));
                $currency    = trim((string) ($row['currency_code'] ?? ''));
                $paymentDays = $row['payment_terms_days'] ?? null;
                $deliveryT   = trim((string) ($row['delivery_type'] ?? ''));
                $notes       = (string) ($row['notes'] ?? '');

                // Validaciones obligatorias.
                if ($supplierKey === '') {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('purchase_orders.supplier') . ' obligatorio.', 'value' => '—'];
                    continue;
                }
                $supplierId = $this->supplierCache[$supplierKey] ?? null;
                if ($supplierId === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'Proveedor no encontrado.', 'value' => $row['supplier_name'] ?? ''];
                    continue;
                }

                if ($whKeyRaw === '') {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('purchase_orders.warehouse') . ' obligatorio.', 'value' => '—'];
                    continue;
                }
                $whUpper = mb_strtoupper($whKeyRaw);
                $whKey   = $this->normalizeKey($whKeyRaw);
                $warehouseId = $this->warehouseCache[$whUpper] ?? ($this->warehouseCache[$whKey] ?? null);
                if ($warehouseId === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'Almacen no encontrado.', 'value' => $whKeyRaw];
                    continue;
                }

                if (!in_array($status, PurchaseOrder::STATUSES, true)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'Status invalido.', 'value' => $status];
                    continue;
                }

                if ($orderDate === '' || !strtotime($orderDate)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'order_date invalido.', 'value' => $orderDate];
                    continue;
                }
                if ($etaDate !== '' && !strtotime($etaDate)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'expected_delivery_date invalido.', 'value' => $etaDate];
                    continue;
                }

                if (mb_strlen($notes) > 2000) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => 'notes supera 2000 caracteres.', 'value' => mb_substr($notes, 0, 40) . '…'];
                    continue;
                }

                // Dedup in-file por reference (case-insensitive).
                if ($reference !== '') {
                    $refKey = mb_strtolower($reference);
                    if (isset($seenInFileByRef[$refKey])) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByRef[$refKey]]),
                            'value'   => $reference,
                        ];
                        continue;
                    }
                    $seenInFileByRef[$refKey] = $absoluteRow;
                }

                // Lookup existente por reference (case-insensitive) scoped al tenant.
                $existing = null;
                if ($reference !== '') {
                    $existing = $this->findExistingByReferenceInsensitive($reference);
                }

                $payload = [
                    'supplier_company_id'    => $supplierId,
                    'warehouse_id'           => $warehouseId,
                    'status'                 => $status,
                    'order_date'             => $orderDate,
                    'expected_delivery_date' => $etaDate !== '' ? $etaDate : null,
                    'currency_code'          => $currency !== '' ? mb_strtoupper($currency) : null,
                    'payment_terms_days'     => $paymentDays !== null && $paymentDays !== '' ? (int) $paymentDays : null,
                    'delivery_type'          => $deliveryT !== '' ? $deliveryT : null,
                    'notes'                  => $notes !== '' ? $notes : null,
                ];

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'       => $absoluteRow,
                            'reference' => $existing->reference,
                            'action'    => 'skipped',
                        ];
                        continue;
                    }

                    $patch = [];
                    foreach ($payload as $k => $v) {
                        if ((string) ($existing->{$k} ?? '') !== (string) ($v ?? '')) {
                            $patch[$k] = $v;
                        }
                    }
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = [
                        'row'       => $absoluteRow,
                        'reference' => $existing->reference,
                        'action'    => 'updated',
                    ];
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $reference !== '' ? $reference : ($row['supplier_name'] ?? ''),
                            ];
                            continue;
                        }
                    }

                    $payload['prefix']    = 'PO';
                    $payload['reference'] = $reference !== '' ? $reference : $this->nextReference();
                    $payload['created_by'] = Auth::id();
                    // tenant_id lo auto-fillea el trait BelongsToTenant.

                    PurchaseOrder::create($payload);

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = [
                        'row'       => $absoluteRow,
                        'reference' => $payload['reference'],
                        'action'    => 'created',
                    ];
                }
            }

            if ($this->dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function summary(): array
    {
        return [
            'created'      => $this->created,
            'updated'      => $this->updated,
            'skipped'      => $this->skipped,
            'error_count'  => count($this->errors),
            'total_rows'   => $this->created + $this->updated + $this->skipped + count($this->errors),
            'errors'       => array_slice($this->errors, 0, 50),
            'preview'      => array_slice($this->preview, 0, 100),
            'dry_run'      => $this->dryRun,
        ];
    }

    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    protected function findExistingByReferenceInsensitive(string $reference): ?PurchaseOrder
    {
        $query = PurchaseOrder::query()->withoutGlobalScopes();
        if ($this->tenantId !== null) {
            $query->where('purchase_orders.tenant_id', $this->tenantId);
        }
        $query->whereRaw('LOWER(purchase_orders.reference) = LOWER(?)', [$reference]);
        return $query->first();
    }

    protected function nextReference(): string
    {
        $year  = now()->year;
        $count = PurchaseOrder::withoutGlobalScopes()
            ->when($this->tenantId !== null, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->whereYear('created_at', $year)
            ->count() + 1;
        return sprintf('PO-%d-%04d', $year, $count);
    }
}
