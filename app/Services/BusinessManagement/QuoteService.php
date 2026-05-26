<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Quotes\BulkQuotesActionJob;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * QuoteService — operaciones de negocio del modulo Quotes.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class QuoteService
{
    public function create(array $data): Quote
    {
        $quote = new Quote($data);
        $quote->created_by = auth()->id();
        $quote->save();
        return $quote;
    }

    public function update(Quote $quote, array $data): Quote
    {
        $quote->update($data);
        return $quote;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Quote $quote, string $reason): void
    {
        $quote->deleted_description = $reason;
        $quote->deleted_by          = auth()->id();
        $quote->is_active           = false;
        $quote->saveQuietly();
        $quote->delete();
    }

    public function restore(Quote $quote): Quote
    {
        $quote->deleted_description = null;
        $quote->deleted_by          = null;
        $quote->restore();
        return $quote;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Quote $quote, string $reason): void
    {
        DB::transaction(function () use ($quote, $reason) {
            $locked = Quote::onlyTrashed()->where('id', $quote->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Quote {$quote->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Quote::class,
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
                'module'         => 'quotes',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Quote $quote): ?Quote
    {
        $base    = $quote->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($quote, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Quote::query()
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
            $clone = new Quote($quote->only($cloneAttrs));
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
    // BulkQuotesActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkQuotesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkQuotesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $quotes  = Quote::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($quotes as $quote) {
            $this->delete($quote, $reason);
            $deletedIds[] = $quote->id;
        }
        return ['queued' => false, 'count' => $quotes->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkQuotesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $quotes = Quote::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($quotes as $quote) {
            if ((bool) $quote->is_active === $isActive) continue;
            $quote->update(['is_active' => $isActive]);
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
            BulkQuotesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $quotes = Quote::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($quotes as $quote) {
            $this->restore($quote);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $quotes->count()];
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
        $quotes = Quote::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($quotes as $quote) {
            $this->restore($quote);
            $restored[] = $quote->id;
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
            $byId  = Quote::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $quote = $byId[$change['id']] ?? null;
                if (!$quote) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $quote->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $quote->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
