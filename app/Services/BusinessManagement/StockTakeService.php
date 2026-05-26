<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\StockTakes\BulkStockTakesActionJob;
use App\Models\AuditLog;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use Illuminate\Support\Facades\DB;

/**
 * StockTakeService — operaciones de negocio del modulo Stock Takes.
 *
 * Clon adaptado del patron SalesOrderService: el controller queda thin y delega
 * aqui toda la mutacion de datos. Diferencia clave vs SalesOrder: StockTake
 * tiene lineas (StockTakeLine) auto-generadas desde StockLevel del warehouse.
 * Al completar el conteo, las diferencias generan stock_movements de ajuste.
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class StockTakeService
{
    /**
     * Crea un conteo y sus lineas iniciales desde el StockLevel del warehouse.
     */
    public function create(array $data): StockTake
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->extractHeaderPayload($data);
            $headerData['created_by'] = auth()->id();
            $headerData['tenant_id']  = auth()->user()?->tenant_id;
            $headerData['started_at'] = ($headerData['status'] ?? 'draft') !== 'draft' ? now() : null;

            $take = StockTake::create($headerData);

            // Auto-genera lineas desde el StockLevel del warehouse.
            $levels = StockLevel::where('warehouse_id', $take->warehouse_id)->get();
            foreach ($levels as $sl) {
                StockTakeLine::create([
                    'stock_take_id' => $take->id,
                    'product_id'    => $sl->product_id,
                    'variant_id'    => $sl->variant_id ?? null,
                    'qty_system'    => $sl->qty_on_hand,
                    'qty_counted'   => null,
                    'variance'      => 0,
                ]);
            }

            return $take->fresh('lines');
        });
    }

    /**
     * Actualiza el conteo. Si el caller mando `lines`, sincroniza qty_counted
     * + variance. Si el status cambia a 'completed' por primera vez, genera
     * stock_movements de ajuste para cada linea con variance != 0.
     */
    public function update(StockTake $take, array $data): StockTake
    {
        return DB::transaction(function () use ($take, $data) {
            // Sincroniza qty_counted en las lineas si vienen.
            if (array_key_exists('lines', $data) && is_array($data['lines'])) {
                foreach ($data['lines'] as $l) {
                    $line = StockTakeLine::find($l['id']);
                    if (!$line || $line->stock_take_id !== $take->id) continue;
                    $counted = isset($l['qty_counted']) && $l['qty_counted'] !== null ? (float) $l['qty_counted'] : null;
                    $line->update([
                        'qty_counted' => $counted,
                        'variance'    => $counted !== null ? round($counted - (float) $line->qty_system, 4) : 0,
                        'note'        => $l['note'] ?? null,
                    ]);
                }
            }

            $patch = $this->extractHeaderPayload($data);
            $newStatus = $patch['status'] ?? $take->status;

            // Transicion a completed por primera vez → genera ajustes.
            if ($newStatus === 'completed' && $take->status !== 'completed') {
                $patch['completed_at'] = now();
                $patch['completed_by'] = auth()->id();
                $this->generateAdjustments($take);
            }

            $take->update($patch);
            return $take->fresh('lines');
        });
    }

    /**
     * Genera stock_movements de ajuste por cada linea con variance != 0 y
     * actualiza el StockLevel correspondiente.
     */
    protected function generateAdjustments(StockTake $take): void
    {
        foreach ($take->lines()->whereNotNull('qty_counted')->get() as $line) {
            if (abs((float) $line->variance) < 0.0001) continue;
            $level = StockLevel::firstOrCreate(
                ['warehouse_id' => $take->warehouse_id, 'product_id' => $line->product_id, 'variant_id' => $line->variant_id ?? null],
                ['qty_on_hand' => 0, 'tenant_id' => $take->tenant_id]
            );
            $level->update([
                'qty_on_hand'      => $line->qty_counted,
                'last_movement_at' => now(),
            ]);
            StockMovement::create([
                'warehouse_id'     => $take->warehouse_id,
                'product_id'       => $line->product_id,
                'variant_id'       => $line->variant_id ?? null,
                'type'             => 'adjustment',
                'quantity'         => abs((float) $line->variance),
                'source_type'      => StockTake::class,
                'source_id'        => $take->id,
                'source_reference' => $take->reference,
                'note'             => __('stock_takes.adjustment_note', ['variance' => $line->variance]),
                'moved_at'         => now(),
                'tenant_id'        => $take->tenant_id,
                'created_by'       => auth()->id(),
            ]);
        }
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(StockTake $take, string $reason): void
    {
        $take->deleted_description = $reason;
        $take->deleted_by          = auth()->id();
        $take->saveQuietly();
        $take->delete();
    }

    public function restore(StockTake $take): StockTake
    {
        $take->deleted_description = null;
        $take->deleted_by          = null;
        $take->restore();
        return $take;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(StockTake $take, string $reason): void
    {
        DB::transaction(function () use ($take, $reason) {
            $locked = StockTake::onlyTrashed()->where('id', $take->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("StockTake {$take->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => StockTake::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'reference'    => $locked->reference,
                    'warehouse_id' => $locked->warehouse_id,
                    'status'       => $locked->status,
                    'slug'         => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'stock_takes',
                'created_at'     => now(),
            ]);

            // Cascadea las lineas antes del forceDelete (la migration ya tiene
            // ON DELETE CASCADE; defense in depth).
            StockTakeLine::where('stock_take_id', $locked->id)->delete();
            $locked->forceDelete();
        });
    }

    /**
     * Clona el conteo (header + lineas vacias, qty_counted null). Reference
     * recibe sufijo "(copia)" con sanity guard de 100 intentos. El clon
     * arranca en status 'draft'.
     */
    public function duplicate(StockTake $take): ?StockTake
    {
        $base    = $take->reference . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($take, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = StockTake::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(reference)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(reference) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $take->loadMissing('lines');

            $clone = new StockTake($take->only([
                'warehouse_id', 'note',
            ]));
            $clone->reference  = $candidate;
            $clone->status     = 'draft';
            $clone->started_at = null;
            $clone->created_by = auth()->id();
            $clone->save();

            // Clonar lineas (qty_system se preserva, qty_counted/variance reseteados)
            foreach ($take->lines as $line) {
                StockTakeLine::create([
                    'stock_take_id' => $clone->id,
                    'product_id'    => $line->product_id,
                    'variant_id'    => $line->variant_id ?? null,
                    'qty_system'    => $line->qty_system,
                    'qty_counted'   => null,
                    'variance'      => 0,
                ]);
            }

            return $clone->fresh('lines');
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkStockTakesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkStockTakesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $takes      = StockTake::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($takes as $take) {
            $this->delete($take, $reason);
            $deletedIds[] = $take->id;
        }
        return ['queued' => false, 'count' => $takes->count(), 'deleted' => $deletedIds];
    }

    /**
     * Bulk update de status. Como SalesOrder, mantenemos el nombre
     * bulkSetActive por consistencia con el patron — el payload pasa el
     * status objetivo.
     *
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, string $status): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkStockTakesActionJob::dispatch(
                (int) auth()->id(),
                'set_status',
                $ids,
                ['status' => $status],
            );
            return ['queued' => true, 'count' => $count];
        }

        $takes   = StockTake::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($takes as $take) {
            if ((string) $take->status === $status) continue;
            $patch = ['status' => $status];
            if ($status !== 'draft' && $take->started_at === null) {
                $patch['started_at'] = now();
            }
            $take->update($patch);
            $changed++;
        }
        return ['queued' => false, 'count' => $count, 'changed' => $changed];
    }

    /**
     * @return array{queued: bool, count: int, restored?: int}
     */
    public function bulkRestore(array $ids): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkStockTakesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $takes = StockTake::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($takes as $take) {
            $this->restore($take);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $takes->count()];
    }

    /**
     * Undo dentro del window de 60s. Solo restaura filas con deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $takes = StockTake::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($takes as $take) {
            $this->restore($take);
            $restored[] = $take->id;
        }
        return $restored;
    }

    /**
     * Batch update de reference + status. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;
        $allowedKeys = ['reference', 'status'];

        DB::transaction(function () use ($changes, &$touched, $allowedKeys) {
            $ids  = array_column($changes, 'id');
            $byId = StockTake::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $take = $byId[$change['id']] ?? null;
                if (!$take) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip($allowedKeys)),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $take->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                // Si cambia status fuera de draft y started_at es null, marcarlo.
                if (isset($patch['status']) && $patch['status'] !== 'draft' && $take->started_at === null) {
                    $patch['started_at'] = now();
                }

                $take->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /** Extrae solo los campos del header. */
    protected function extractHeaderPayload(array $data): array
    {
        $keys = ['reference', 'warehouse_id', 'status', 'note'];
        return collect($data)->only($keys)->toArray();
    }
}
