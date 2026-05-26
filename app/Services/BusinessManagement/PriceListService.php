<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\PriceLists\BulkPriceListsActionJob;
use App\Models\AuditLog;
use App\Models\PriceList;
use Illuminate\Support\Facades\DB;

/**
 * PriceListService — operaciones de negocio del modulo PriceLists.
 *
 * Clon del patron de DiscountService/WarehouseService: el controller queda
 * thin y delega aqui toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class PriceListService
{
    public function create(array $data): PriceList
    {
        $priceList = new PriceList($data);
        $priceList->created_by = auth()->id();
        $priceList->save();
        return $priceList;
    }

    public function update(PriceList $priceList, array $data): PriceList
    {
        $priceList->update($data);
        return $priceList;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(PriceList $priceList, string $reason): void
    {
        $priceList->deleted_description = $reason;
        $priceList->deleted_by          = auth()->id();
        $priceList->is_active           = false;
        $priceList->saveQuietly();
        $priceList->delete();
    }

    public function restore(PriceList $priceList): PriceList
    {
        $priceList->deleted_description = null;
        $priceList->deleted_by          = null;
        $priceList->restore();
        return $priceList;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(PriceList $priceList, string $reason): void
    {
        DB::transaction(function () use ($priceList, $reason) {
            $locked = PriceList::onlyTrashed()->where('id', $priceList->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("PriceList {$priceList->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => PriceList::class,
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
                'module'         => 'price_lists',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona la price list. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(PriceList $priceList): ?PriceList
    {
        $base    = $priceList->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($priceList, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = PriceList::query()
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
                'description', 'currency_code',
                'valid_from', 'valid_until', 'global_discount_pct',
                'priority', 'is_active',
            ];
            $clone = new PriceList($priceList->only($cloneAttrs));
            $clone->name       = $candidate;
            // Una sola lista puede ser default por tenant — el clon nunca hereda
            // el flag para no romper el invariante.
            $clone->is_default = false;
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
    // BulkPriceListsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPriceListsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPriceListsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $priceLists = PriceList::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($priceLists as $priceList) {
            $this->delete($priceList, $reason);
            $deletedIds[] = $priceList->id;
        }
        return ['queued' => false, 'count' => $priceLists->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPriceListsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $priceLists = PriceList::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($priceLists as $priceList) {
            if ((bool) $priceList->is_active === $isActive) continue;
            $priceList->update(['is_active' => $isActive]);
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
            BulkPriceListsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $priceLists = PriceList::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($priceLists as $priceList) {
            $this->restore($priceList);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $priceLists->count()];
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
        $priceLists = PriceList::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($priceLists as $priceList) {
            $this->restore($priceList);
            $restored[] = $priceList->id;
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
            $ids  = array_column($changes, 'id');
            $byId = PriceList::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $priceList = $byId[$change['id']] ?? null;
                if (!$priceList) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $priceList->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $priceList->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
