<?php

namespace App\Services\Crm;

use App\Jobs\Crm\Pipelines\BulkPipelinesActionJob;
use App\Models\AuditLog;
use App\Models\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * PipelineService — operaciones de negocio del modulo Pipelines.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class PipelineService
{
    public function create(array $data): Pipeline
    {
        return DB::transaction(function () use ($data) {
            $pipeline = new Pipeline($data);
            $pipeline->created_by = auth()->id();
            $pipeline->save();

            if (!empty($data['is_default'])) {
                $this->unsetOtherDefaults($pipeline);
            }

            return $pipeline;
        });
    }

    public function update(Pipeline $pipeline, array $data): Pipeline
    {
        return DB::transaction(function () use ($pipeline, $data) {
            $pipeline->update($data);

            if (!empty($data['is_default']) && $pipeline->is_default) {
                $this->unsetOtherDefaults($pipeline);
            }

            return $pipeline;
        });
    }

    /**
     * Garantiza la regla "un solo default por workspace": cualquier otro
     * pipeline del mismo tenant con is_default=true se baja a false.
     *
     * Se ejecuta dentro de la transaccion de create/update para evitar
     * ventana donde haya dos defaults simultaneos. saveQuietly equivalente
     * no se usa: si admin marca un nuevo default queremos audit log del
     * cambio en el viejo default tambien.
     */
    protected function unsetOtherDefaults(Pipeline $current): void
    {
        Pipeline::query()
            ->where('tenant_id', $current->tenant_id)
            ->where('id', '!=', $current->id)
            ->where('is_default', true)
            ->get()
            ->each(fn ($p) => $p->update(['is_default' => false]));
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Pipeline $pipeline, string $reason): void
    {
        $pipeline->deleted_description = $reason;
        $pipeline->deleted_by          = auth()->id();
        $pipeline->is_active           = false;
        $pipeline->saveQuietly();
        $pipeline->delete();
    }

    public function restore(Pipeline $pipeline): Pipeline
    {
        $pipeline->deleted_description = null;
        $pipeline->deleted_by          = null;
        $pipeline->restore();
        return $pipeline;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Pipeline $pipeline, string $reason): void
    {
        DB::transaction(function () use ($pipeline, $reason) {
            $locked = Pipeline::onlyTrashed()->where('id', $pipeline->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Pipeline {$pipeline->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Pipeline::class,
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
                'module'         => 'pipelines',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Pipeline $pipeline): ?Pipeline
    {
        $base    = $pipeline->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($pipeline, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Pipeline::query()
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
            $clone = new Pipeline($pipeline->only($cloneAttrs));
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
    // BulkPipelinesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPipelinesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPipelinesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $pipelines  = Pipeline::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($pipelines as $pipeline) {
            $this->delete($pipeline, $reason);
            $deletedIds[] = $pipeline->id;
        }
        return ['queued' => false, 'count' => $pipelines->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPipelinesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $pipelines = Pipeline::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($pipelines as $pipeline) {
            if ((bool) $pipeline->is_active === $isActive) continue;
            $pipeline->update(['is_active' => $isActive]);
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
            BulkPipelinesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $pipelines = Pipeline::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($pipelines as $pipeline) {
            $this->restore($pipeline);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $pipelines->count()];
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
        $pipelines = Pipeline::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($pipelines as $pipeline) {
            $this->restore($pipeline);
            $restored[] = $pipeline->id;
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
            $byId  = Pipeline::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $pipeline = $byId[$change['id']] ?? null;
                if (!$pipeline) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $pipeline->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $pipeline->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
