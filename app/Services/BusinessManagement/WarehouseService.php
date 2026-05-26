<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Warehouses\BulkWarehousesActionJob;
use App\Models\AuditLog;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * WarehouseService — operaciones de negocio del modulo Warehouses.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class WarehouseService
{
    public function create(array $data): Warehouse
    {
        $warehouse = new Warehouse($data);
        $warehouse->created_by = auth()->id();
        $warehouse->save();
        return $warehouse;
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);
        return $warehouse;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Warehouse $warehouse, string $reason): void
    {
        $warehouse->deleted_description = $reason;
        $warehouse->deleted_by          = auth()->id();
        $warehouse->is_active           = false;
        $warehouse->saveQuietly();
        $warehouse->delete();
    }

    public function restore(Warehouse $warehouse): Warehouse
    {
        $warehouse->deleted_description = null;
        $warehouse->deleted_by          = null;
        $warehouse->restore();
        return $warehouse;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Warehouse $warehouse, string $reason): void
    {
        DB::transaction(function () use ($warehouse, $reason) {
            $locked = Warehouse::onlyTrashed()->where('id', $warehouse->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Warehouse {$warehouse->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Warehouse::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name' => $locked->name,
                    'slug' => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'warehouses',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Warehouse $warehouse): ?Warehouse
    {
        $base    = $warehouse->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($warehouse, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Warehouse::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(name) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'is_active',
            ];
            $clone = new Warehouse($warehouse->only($cloneAttrs));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkWarehousesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkWarehousesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkWarehousesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $warehouses  = Warehouse::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($warehouses as $warehouse) {
            $this->delete($warehouse, $reason);
            $deletedIds[] = $warehouse->id;
        }
        return ['queued' => false, 'count' => $warehouses->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkWarehousesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $warehouses = Warehouse::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($warehouses as $warehouse) {
            if ((bool) $warehouse->is_active === $isActive) continue;
            $warehouse->update(['is_active' => $isActive]);
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
            BulkWarehousesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $warehouses = Warehouse::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($warehouses as $warehouse) {
            $this->restore($warehouse);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $warehouses->count()];
    }

    /**
     * Undo dentro del window de 60s. Defense in depth: solo restaura las filas
     * que matchean deleted_by = userId, no cualquier id del claim.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $warehouses = Warehouse::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($warehouses as $warehouse) {
            $this->restore($warehouse);
            $restored[] = $warehouse->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + is_active. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids   = array_column($changes, 'id');
            $byId  = Warehouse::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $warehouse = $byId[$change['id']] ?? null;
                if (!$warehouse) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $warehouse->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $warehouse->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
