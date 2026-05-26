<?php

namespace App\Services\Crm;

use App\Jobs\Crm\Deals\BulkDealsActionJob;
use App\Models\AuditLog;
use App\Models\Deal;
use Illuminate\Support\Facades\DB;

/**
 * DealService — operaciones de negocio del modulo Deals.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class DealService
{
    public function create(array $data): Deal
    {
        // Auto-inherit probability_pct desde el stage cuando no viene explicita.
        // Regla de negocio: si el sales rep no override la probabilidad, el
        // forecast usa el peso default del stage (kanban kanban-driven forecast).
        if (!array_key_exists('probability_pct', $data) || $data['probability_pct'] === null) {
            $stageProb = $this->stageProbability($data['stage_id'] ?? null);
            if ($stageProb !== null) {
                $data['probability_pct'] = $stageProb;
            }
        }

        // Auto-set won_at/lost_at en create si el deal se crea ya cerrado
        // (caso poco comun pero existe: backfill desde import o data entry).
        $data = $this->applyStatusTimestamps($data);

        $deal = new Deal($data);
        $deal->created_by = auth()->id();
        $deal->save();
        return $deal;
    }

    public function update(Deal $deal, array $data): Deal
    {
        // Re-inheritance al cambiar de stage: si el caller NO override
        // probability_pct, y el stage_id cambio, heredamos del stage nuevo.
        if (
            (!array_key_exists('probability_pct', $data) || $data['probability_pct'] === null)
            && array_key_exists('stage_id', $data)
            && (int) $data['stage_id'] !== (int) $deal->stage_id
        ) {
            $stageProb = $this->stageProbability($data['stage_id']);
            if ($stageProb !== null) {
                $data['probability_pct'] = $stageProb;
            }
        }

        // Workflow won/lost: setear timestamps cuando el status cambia.
        $data = $this->applyStatusTimestamps($data, $deal);

        $deal->update($data);
        return $deal;
    }

    /**
     * Devuelve la probability_pct del stage indicado, o null si no se puede
     * resolver (stage_id ausente o stage borrado). Encapsulado para reuso
     * en create + update.
     */
    protected function stageProbability(?int $stageId): ?int
    {
        if (!$stageId) return null;
        $prob = \App\Models\PipelineStage::where('id', $stageId)->value('probability_pct');
        return $prob === null ? null : (int) $prob;
    }

    /**
     * Auto-set de won_at/lost_at segun el status del deal. Logica idempotente:
     *   - status=won  + won_at vacio  → won_at = now()
     *   - status=lost + lost_at vacio → lost_at = now()
     *   - status=open  → limpia won_at y lost_at (deal re-abierto)
     *
     * Si el caller mandó un timestamp explicito, se respeta — el sales rep
     * puede registrar la fecha real de cierre que difiere de la del request.
     */
    protected function applyStatusTimestamps(array $data, ?Deal $existing = null): array
    {
        $status = $data['status'] ?? $existing?->status;

        if ($status === 'won') {
            if (empty($data['won_at']) && empty($existing?->won_at)) {
                $data['won_at'] = now();
            }
        } elseif ($status === 'lost') {
            if (empty($data['lost_at']) && empty($existing?->lost_at)) {
                $data['lost_at'] = now();
            }
        } elseif ($status === 'open') {
            // Reapertura: limpio ambos timestamps para que no queden datos
            // contradictorios (status=open con won_at seteado seria un bug).
            if ($existing && ($existing->won_at || $existing->lost_at)) {
                $data['won_at']  = null;
                $data['lost_at'] = null;
            }
        }

        return $data;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Deal $deal, string $reason): void
    {
        $deal->deleted_description = $reason;
        $deal->deleted_by          = auth()->id();
        $deal->is_active           = false;
        $deal->saveQuietly();
        $deal->delete();
    }

    public function restore(Deal $deal): Deal
    {
        $deal->deleted_description = null;
        $deal->deleted_by          = null;
        $deal->restore();
        return $deal;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Deal $deal, string $reason): void
    {
        DB::transaction(function () use ($deal, $reason) {
            $locked = Deal::onlyTrashed()->where('id', $deal->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Deal {$deal->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Deal::class,
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
                'module'         => 'deals',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Deal $deal): ?Deal
    {
        $base    = $deal->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($deal, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Deal::query()
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
            $clone = new Deal($deal->only($cloneAttrs));
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
    // BulkDealsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkDealsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDealsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $deals  = Deal::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($deals as $deal) {
            $this->delete($deal, $reason);
            $deletedIds[] = $deal->id;
        }
        return ['queued' => false, 'count' => $deals->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDealsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $deals = Deal::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($deals as $deal) {
            if ((bool) $deal->is_active === $isActive) continue;
            $deal->update(['is_active' => $isActive]);
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
            BulkDealsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $deals = Deal::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($deals as $deal) {
            $this->restore($deal);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $deals->count()];
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
        $deals = Deal::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($deals as $deal) {
            $this->restore($deal);
            $restored[] = $deal->id;
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
            $byId  = Deal::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $deal = $byId[$change['id']] ?? null;
                if (!$deal) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $deal->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $deal->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
