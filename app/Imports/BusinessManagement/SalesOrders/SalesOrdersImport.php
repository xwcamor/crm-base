<?php

namespace App\Imports\BusinessManagement\SalesOrders;

use App\Models\Company;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports Sales Orders desde .xlsx/.csv.
 *
 * Limitaciones de scope:
 *   - El import crea OVs SIN lineas (items). El usuario debe entrar al detalle
 *     y agregar lineas manualmente. Importar lineas requeriria un esquema
 *     padre-hijo (multi-sheet o concat) que sale del Tier-1 base.
 *
 * 2 layers de dedup:
 *   1. In-file: por reference normalizado
 *   2. DB: lookup case+accent insensitive contra el tenant
 *
 * Modes: 'create_only' | 'update_or_create'
 */
class SalesOrdersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    /** @var array<int, array> */
    public array $preview = [];

    protected ?int $tenantId;
    protected int $maxRecords;
    protected int $currentCount;

    /** Cache normalizedName(company) → companyId */
    protected array $companyNameCache = [];

    /** Cache warehouseCode → warehouseId */
    protected array $warehouseCodeCache = [];

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
            ? SalesOrder::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : SalesOrder::withoutGlobalScopes()->count();

        // Precarga companies del tenant.
        $companyQuery = Company::query();
        if ($this->tenantId !== null) $companyQuery->where('tenant_id', $this->tenantId);
        $companyQuery->get(['id', 'name'])->each(function ($c) {
            $this->companyNameCache[$this->normalizeKey($c->name)] = $c->id;
        });

        // Precarga warehouses del tenant.
        $whQuery = Warehouse::query();
        if ($this->tenantId !== null) $whQuery->where('tenant_id', $this->tenantId);
        $whQuery->get(['id', 'code'])->each(function ($w) {
            if ($w->code) {
                $this->warehouseCodeCache[mb_strtoupper(trim($w->code))] = $w->id;
            }
        });
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFile = [];
            $newRecords = 0;

            $validStatuses        = SalesOrder::STATUSES;
            $validPaymentStatuses = SalesOrder::PAYMENT_STATUSES;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $reference     = $this->normalizeString($row['reference'] ?? null);
                $companyName   = $this->normalizeString($row['company_name'] ?? null);
                $warehouseCode = $this->normalizeIso($row['warehouse_code'] ?? null);
                $status        = $this->normalizeString($row['status'] ?? null) ?? 'pending';
                $paymentStatus = $this->normalizeString($row['payment_status'] ?? null) ?? 'unpaid';
                $orderDate     = $this->normalizeDate($row['order_date'] ?? null) ?? now()->toDateString();
                $expDeliv      = $this->normalizeDate($row['expected_delivery_date'] ?? null);
                $currencyCode  = $this->normalizeIso($row['currency_code'] ?? null);
                $notes         = $this->normalizeString($row['notes'] ?? null);

                if ($companyName === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_company_required'), 'value' => '—'];
                    continue;
                }
                if ($warehouseCode === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_warehouse_required'), 'value' => '—'];
                    continue;
                }

                $companyId = $this->companyNameCache[$this->normalizeKey($companyName)] ?? null;
                if (!$companyId) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_company_not_found'), 'value' => $companyName];
                    continue;
                }
                $warehouseId = $this->warehouseCodeCache[$warehouseCode] ?? null;
                if (!$warehouseId) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_warehouse_not_found'), 'value' => $warehouseCode];
                    continue;
                }

                if (!in_array($status, $validStatuses, true)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_invalid_status'), 'value' => $status];
                    continue;
                }
                if (!in_array($paymentStatus, $validPaymentStatuses, true)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('sales_orders.import_invalid_payment_status'), 'value' => $paymentStatus];
                    continue;
                }

                if ($reference !== null) {
                    $refKey = $this->normalizeKey($reference);
                    if (isset($seenInFile[$refKey])) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFile[$refKey]]),
                            'value'   => $reference,
                        ];
                        continue;
                    }
                    $seenInFile[$refKey] = $absoluteRow;
                }

                $existing = $reference !== null ? $this->findExistingByReference($reference) : null;

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = $this->previewRow($absoluteRow, $reference, $companyName, $status, 'skipped');
                        continue;
                    }

                    $patch = [];
                    if ((string) $existing->status !== $status)                 $patch['status'] = $status;
                    if ((string) $existing->payment_status !== $paymentStatus)  $patch['payment_status'] = $paymentStatus;
                    if ($existing->company_id !== $companyId)                   $patch['company_id'] = $companyId;
                    if ($existing->warehouse_id !== $warehouseId)               $patch['warehouse_id'] = $warehouseId;
                    if ($currencyCode !== null && $existing->currency_code !== $currencyCode) $patch['currency_code'] = $currencyCode;
                    if ($notes !== null && $existing->notes !== $notes)         $patch['notes'] = $notes;
                    if ($expDeliv !== null)                                     $patch['expected_delivery_date'] = $expDeliv;

                    if (!empty($patch)) $existing->fill($patch)->save();

                    $this->updated++;
                    $this->preview[] = $this->previewRow($absoluteRow, $reference, $companyName, $status, 'updated');
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecords) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $reference ?? $companyName,
                            ];
                            continue;
                        }
                    }

                    $autoRef = $reference ?? $this->nextReference();

                    SalesOrder::create([
                        'reference'              => $autoRef,
                        'prefix'                 => 'OV',
                        'company_id'             => $companyId,
                        'warehouse_id'           => $warehouseId,
                        'status'                 => $status,
                        'payment_status'         => $paymentStatus,
                        'order_date'             => $orderDate,
                        'expected_delivery_date' => $expDeliv,
                        'currency_code'          => $currencyCode,
                        'subtotal'               => 0,
                        'discount_total'         => 0,
                        'tax_total'              => 0,
                        'shipping_cost'          => 0,
                        'grand_total'            => 0,
                        'payment_terms_days'     => 30,
                        'notes'                  => $notes,
                        'created_by'             => Auth::id(),
                    ]);

                    $newRecords++;
                    $this->created++;
                    $this->preview[] = $this->previewRow($absoluteRow, $autoRef, $companyName, $status, 'created');
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

    protected function previewRow(int $row, ?string $ref, string $company, string $status, string $action): array
    {
        return [
            'row'       => $row,
            'reference' => $ref,
            'company'   => $company,
            'status'    => $status,
            'action'    => $action,
        ];
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    protected function normalizeIso(mixed $value): ?string
    {
        if ($value === null) return null;
        $v = mb_strtoupper(trim((string) $value));
        return $v === '' ? null : $v;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    protected function findExistingByReference(string $reference): ?SalesOrder
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = SalesOrder::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('sales_orders.tenant_id', $this->tenantId);
        }

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(sales_orders.reference)) = unaccent(LOWER(?))', [$reference]);
        } else {
            $query->whereRaw('LOWER(sales_orders.reference) = LOWER(?)', [$reference]);
        }

        return $query->first();
    }

    protected function nextReference(): string
    {
        $year  = now()->year;
        $count = SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;
        return sprintf('OV-%d-%04d', $year, $count);
    }
}
