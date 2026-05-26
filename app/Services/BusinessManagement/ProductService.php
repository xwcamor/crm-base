<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Products\BulkProductsActionJob;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * ProductService — operaciones de negocio del modulo Products.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class ProductService
{
    public function create(array $data): Product
    {
        $product = new Product($data);
        $product->created_by = auth()->id();
        $product->save();
        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Product $product, string $reason): void
    {
        $product->deleted_description = $reason;
        $product->deleted_by          = auth()->id();
        $product->is_active           = false;
        $product->saveQuietly();
        $product->delete();
    }

    public function restore(Product $product): Product
    {
        $product->deleted_description = null;
        $product->deleted_by          = null;
        $product->restore();
        return $product;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Product $product, string $reason): void
    {
        DB::transaction(function () use ($product, $reason) {
            $locked = Product::onlyTrashed()->where('id', $product->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Product {$product->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Product::class,
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
                'module'         => 'products',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Product $product): ?Product
    {
        $base    = $product->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($product, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Product::query()
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
            $clone = new Product($product->only($cloneAttrs));
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
    // BulkProductsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkProductsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $products  = Product::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($products as $product) {
            $this->delete($product, $reason);
            $deletedIds[] = $product->id;
        }
        return ['queued' => false, 'count' => $products->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $products = Product::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($products as $product) {
            if ((bool) $product->is_active === $isActive) continue;
            $product->update(['is_active' => $isActive]);
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
            BulkProductsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $products = Product::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($products as $product) {
            $this->restore($product);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $products->count()];
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
        $products = Product::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($products as $product) {
            $this->restore($product);
            $restored[] = $product->id;
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
            $byId  = Product::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $product = $byId[$change['id']] ?? null;
                if (!$product) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $product->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $product->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
