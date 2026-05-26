<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\TaxClasses\BulkTaxClassesActionJob;
use App\Models\AuditLog;
use App\Models\TaxClass;
use Illuminate\Support\Facades\DB;

/**
 * TaxClassService — operaciones de negocio del modulo TaxClasses.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class TaxClassService
{
    public function create(array $data): TaxClass
    {
        $taxClass = new TaxClass($data);
        $taxClass->created_by = auth()->id();
        $taxClass->save();
        return $taxClass;
    }

    public function update(TaxClass $taxClass, array $data): TaxClass
    {
        $taxClass->update($data);
        return $taxClass;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(TaxClass $taxClass, string $reason): void
    {
        $taxClass->deleted_description = $reason;
        $taxClass->deleted_by          = auth()->id();
        $taxClass->is_active           = false;
        $taxClass->saveQuietly();
        $taxClass->delete();
    }

    public function restore(TaxClass $taxClass): TaxClass
    {
        $taxClass->deleted_description = null;
        $taxClass->deleted_by          = null;
        $taxClass->restore();
        return $taxClass;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(TaxClass $taxClass, string $reason): void
    {
        DB::transaction(function () use ($taxClass, $reason) {
            $locked = TaxClass::onlyTrashed()->where('id', $taxClass->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("TaxClass {$taxClass->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => TaxClass::class,
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
                'module'         => 'tax_classes',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(TaxClass $taxClass): ?TaxClass
    {
        $base    = $taxClass->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($taxClass, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = TaxClass::query()
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
            $clone = new TaxClass($taxClass->only($cloneAttrs));
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
    // BulkTaxClassesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkTaxClassesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTaxClassesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $tax_classes  = TaxClass::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($tax_classes as $taxClass) {
            $this->delete($taxClass, $reason);
            $deletedIds[] = $taxClass->id;
        }
        return ['queued' => false, 'count' => $tax_classes->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkTaxClassesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $tax_classes = TaxClass::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($tax_classes as $taxClass) {
            if ((bool) $taxClass->is_active === $isActive) continue;
            $taxClass->update(['is_active' => $isActive]);
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
            BulkTaxClassesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $tax_classes = TaxClass::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($tax_classes as $taxClass) {
            $this->restore($taxClass);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $tax_classes->count()];
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
        $tax_classes = TaxClass::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($tax_classes as $taxClass) {
            $this->restore($taxClass);
            $restored[] = $taxClass->id;
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
            $byId  = TaxClass::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $taxClass = $byId[$change['id']] ?? null;
                if (!$taxClass) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $taxClass->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $taxClass->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
