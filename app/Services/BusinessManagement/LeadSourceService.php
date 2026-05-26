<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\LeadSources\BulkLeadSourcesActionJob;
use App\Models\AuditLog;
use App\Models\LeadSource;
use Illuminate\Support\Facades\DB;

/**
 * LeadSourceService — operaciones de negocio del modulo Lead Sources.
 *
 * Clon del patron ProductCategoryService/DiscountService: controller thin
 * que delega aqui toda la mutacion de datos. Auditable trait dispara los
 * logs en created/updated/deleted/restored; force_delete escribe el audit
 * manual.
 */
class LeadSourceService
{
    public function create(array $data): LeadSource
    {
        $leadSource = new LeadSource($data);
        $leadSource->created_by = auth()->id();
        $leadSource->save();
        return $leadSource;
    }

    public function update(LeadSource $leadSource, array $data): LeadSource
    {
        $leadSource->update($data);
        return $leadSource;
    }

    public function delete(LeadSource $leadSource, string $reason): void
    {
        $leadSource->deleted_description = $reason;
        $leadSource->deleted_by          = auth()->id();
        $leadSource->is_active           = false;
        $leadSource->saveQuietly();
        $leadSource->delete();
    }

    public function restore(LeadSource $leadSource): LeadSource
    {
        $leadSource->deleted_description = null;
        $leadSource->deleted_by          = null;
        $leadSource->restore();
        return $leadSource;
    }

    public function forceDelete(LeadSource $leadSource, string $reason): void
    {
        DB::transaction(function () use ($leadSource, $reason) {
            $locked = LeadSource::onlyTrashed()->where('id', $leadSource->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("LeadSource {$leadSource->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => LeadSource::class,
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
                'module'         => 'lead_sources',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona la fuente. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(LeadSource $leadSource): ?LeadSource
    {
        $base    = $leadSource->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($leadSource, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $q = LeadSource::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(name) = LOWER(?)', [$candidate]),
                    );
                $exists = $q->lockForUpdate()->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'description', 'category', 'sort_order', 'is_active',
            ];
            $clone = new LeadSource($leadSource->only($cloneAttrs));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkLeadSourcesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkLeadSourcesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $leadSources = LeadSource::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($leadSources as $leadSource) {
            $this->delete($leadSource, $reason);
            $deletedIds[] = $leadSource->id;
        }
        return ['queued' => false, 'count' => $leadSources->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkLeadSourcesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $leadSources = LeadSource::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($leadSources as $leadSource) {
            if ((bool) $leadSource->is_active === $isActive) continue;
            $leadSource->update(['is_active' => $isActive]);
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
            BulkLeadSourcesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $leadSources = LeadSource::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($leadSources as $leadSource) {
            $this->restore($leadSource);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $leadSources->count()];
    }

    /**
     * Undo dentro del window de 60s. Solo restaura las filas que matchean
     * deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $leadSources = LeadSource::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($leadSources as $leadSource) {
            $this->restore($leadSource);
            $restored[] = $leadSource->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + is_active.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = LeadSource::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $leadSource = $byId[$change['id']] ?? null;
                if (!$leadSource) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $leadSource->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $leadSource->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
