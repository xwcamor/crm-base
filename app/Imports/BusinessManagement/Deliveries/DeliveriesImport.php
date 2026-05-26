<?php

namespace App\Imports\BusinessManagement\Deliveries;

use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports Deliveries desde .xlsx/.csv.
 *
 * Limitaciones de scope:
 *   - El import crea entregas SIN lineas (delivery_items). El usuario completa
 *     las cantidades a despachar editando la entrega desde el formulario.
 *
 * 3 layers de dedup:
 *   1. In-file: por reference normalizado
 *   2. DB: lookup case+accent insensitive contra el tenant
 *   3. SO lookup: case+accent insensitive contra reference
 *
 * Modes: 'create_only' | 'update_or_create'
 */
class DeliveriesImport implements ToCollection, WithHeadingRow
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

    /** Cache warehouseCode -> warehouseId */
    protected array $warehouseCodeCache = [];

    /** Cache salesOrderRef (normalized) -> [id, warehouse_id] */
    protected array $salesOrderCache = [];

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
            ? Delivery::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : Delivery::withoutGlobalScopes()->count();

        // Precarga warehouses del tenant.
        $whQuery = Warehouse::query();
        if ($this->tenantId !== null) $whQuery->where('tenant_id', $this->tenantId);
        $whQuery->get(['id', 'code'])->each(function ($w) {
            if ($w->code) {
                $this->warehouseCodeCache[mb_strtoupper(trim($w->code))] = $w->id;
            }
        });

        // Precarga sales orders del tenant para lookup rapido.
        $soQuery = SalesOrder::query()->withoutGlobalScopes();
        if ($this->tenantId !== null) $soQuery->where('tenant_id', $this->tenantId);
        $soQuery->get(['id', 'reference', 'warehouse_id'])->each(function ($so) {
            $key = $this->normalizeKey($so->reference);
            $this->salesOrderCache[$key] = ['id' => $so->id, 'warehouse_id' => $so->warehouse_id];
        });
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFile = [];
            $newRecords = 0;

            $validStatuses = Delivery::STATUSES;

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $reference     = $this->normalizeString($row['reference'] ?? null);
                $salesOrderRef = $this->normalizeString($row['sales_order_ref'] ?? null);
                $warehouseCode = $this->normalizeIso($row['warehouse_code'] ?? null);
                $status        = $this->normalizeString($row['status'] ?? null) ?? 'pending';
                $carrier       = $this->normalizeString($row['carrier'] ?? null);
                $tracking      = $this->normalizeString($row['tracking_number'] ?? null);
                $shipMethod    = $this->normalizeString($row['shipping_method'] ?? null);
                $notes         = $this->normalizeString($row['notes'] ?? null);

                if ($salesOrderRef === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('deliveries.import_sales_order_required'), 'value' => '-'];
                    continue;
                }
                if ($warehouseCode === null) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('deliveries.import_warehouse_required'), 'value' => '-'];
                    continue;
                }

                $soKey = $this->normalizeKey($salesOrderRef);
                $so    = $this->salesOrderCache[$soKey] ?? null;
                if (!$so) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('deliveries.import_sales_order_not_found'), 'value' => $salesOrderRef];
                    continue;
                }

                $warehouseId = $this->warehouseCodeCache[$warehouseCode] ?? null;
                if (!$warehouseId) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('deliveries.import_warehouse_not_found'), 'value' => $warehouseCode];
                    continue;
                }

                if (!in_array($status, $validStatuses, true)) {
                    $this->errors[] = ['row' => $absoluteRow, 'message' => __('deliveries.import_invalid_status'), 'value' => $status];
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
                        $this->preview[] = $this->previewRow($absoluteRow, $reference, $salesOrderRef, $warehouseCode, $status, 'skipped');
                        continue;
                    }

                    $patch = [];
                    if ((string) $existing->status !== $status)                                $patch['status'] = $status;
                    if ($existing->sales_order_id !== $so['id'])                                $patch['sales_order_id'] = $so['id'];
                    if ($existing->warehouse_id !== $warehouseId)                              $patch['warehouse_id'] = $warehouseId;
                    if ($carrier !== null && $existing->carrier !== $carrier)                  $patch['carrier'] = $carrier;
                    if ($tracking !== null && $existing->tracking_number !== $tracking)        $patch['tracking_number'] = $tracking;
                    if ($shipMethod !== null && $existing->shipping_method !== $shipMethod)    $patch['shipping_method'] = $shipMethod;
                    if ($notes !== null && $existing->notes !== $notes)                        $patch['notes'] = $notes;

                    if (!empty($patch)) $existing->fill($patch)->save();

                    $this->updated++;
                    $this->preview[] = $this->previewRow($absoluteRow, $reference, $salesOrderRef, $warehouseCode, $status, 'updated');
                } else {
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecords) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $reference ?? $salesOrderRef,
                            ];
                            continue;
                        }
                    }

                    $autoRef = $reference ?? $this->nextReference();

                    Delivery::create([
                        'reference'       => $autoRef,
                        'prefix'          => 'DEL',
                        'sales_order_id'  => $so['id'],
                        'warehouse_id'    => $warehouseId,
                        'status'          => $status,
                        'carrier'         => $carrier,
                        'tracking_number' => $tracking,
                        'shipping_method' => $shipMethod,
                        'shipping_cost'   => 0,
                        'notes'           => $notes,
                        'created_by'      => Auth::id(),
                    ]);

                    $newRecords++;
                    $this->created++;
                    $this->preview[] = $this->previewRow($absoluteRow, $autoRef, $salesOrderRef, $warehouseCode, $status, 'created');
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

    protected function previewRow(int $row, ?string $ref, string $salesOrder, string $warehouse, string $status, string $action): array
    {
        return [
            'row'         => $row,
            'reference'   => $ref,
            'sales_order' => $salesOrder,
            'warehouse'   => $warehouse,
            'status'      => $status,
            'action'      => $action,
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

    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    protected function findExistingByReference(string $reference): ?Delivery
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = Delivery::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('deliveries.tenant_id', $this->tenantId);
        }

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(deliveries.reference)) = unaccent(LOWER(?))', [$reference]);
        } else {
            $query->whereRaw('LOWER(deliveries.reference) = LOWER(?)', [$reference]);
        }

        return $query->first();
    }

    protected function nextReference(): string
    {
        $year  = now()->year;
        $count = Delivery::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;
        return sprintf('DEL-%d-%04d', $year, $count);
    }
}
