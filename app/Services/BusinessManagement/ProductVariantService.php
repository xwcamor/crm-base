<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\ProductVariants\BulkProductVariantsActionJob;
use App\Models\AuditLog;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * ProductVariantService — operaciones de negocio del modulo Product Variants.
 *
 * Clon del patron de ProductCategoryService: controller thin que delega aqui
 * toda la mutacion de datos. Auditable trait dispara los logs en
 * created/updated/deleted/restored; force_delete escribe el audit manual.
 */
class ProductVariantService
{
    public function create(array $data): ProductVariant
    {
        $variant = new ProductVariant($data);
        $variant->created_by = auth()->id();
        $variant->save();
        return $variant;
    }

    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);
        return $variant;
    }

    public function delete(ProductVariant $variant, string $reason): void
    {
        $variant->deleted_description = $reason;
        $variant->deleted_by          = auth()->id();
        $variant->is_active           = false;
        $variant->saveQuietly();
        $variant->delete();
    }

    public function restore(ProductVariant $variant): ProductVariant
    {
        $variant->deleted_description = null;
        $variant->deleted_by          = null;
        $variant->restore();
        return $variant;
    }

    public function forceDelete(ProductVariant $variant, string $reason): void
    {
        DB::transaction(function () use ($variant, $reason) {
            $locked = ProductVariant::onlyTrashed()->where('id', $variant->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("ProductVariant {$variant->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => ProductVariant::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name' => $locked->name,
                    'sku'  => $locked->sku,
                    'slug' => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'product_variants',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona la variante. SKU sufijo "-copia" con sanity guard de 100 intentos.
     */
    public function duplicate(ProductVariant $variant): ?ProductVariant
    {
        $baseSku = $variant->sku . '-' . __('global.duplicate_suffix');

        return DB::transaction(function () use ($variant, $baseSku) {
            $candidate = $baseSku;
            $i = 2;

            while (true) {
                $exists = ProductVariant::query()
                    ->whereRaw('LOWER(sku) = LOWER(?)', [$candidate])
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $baseSku . '-' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'product_id', 'name', 'barcode', 'attributes',
                'cost', 'price', 'low_stock_threshold', 'image_url',
                'sort_order', 'is_active',
            ];
            $clone = new ProductVariant($variant->only($cloneAttrs));
            $clone->sku        = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkProductVariantsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductVariantsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $variants = ProductVariant::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($variants as $variant) {
            $this->delete($variant, $reason);
            $deletedIds[] = $variant->id;
        }
        return ['queued' => false, 'count' => $variants->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductVariantsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $variants = ProductVariant::whereIn('id', $ids)->get();
        $changed  = 0;
        foreach ($variants as $variant) {
            if ((bool) $variant->is_active === $isActive) continue;
            $variant->update(['is_active' => $isActive]);
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
            BulkProductVariantsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $variants = ProductVariant::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($variants as $variant) {
            $this->restore($variant);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $variants->count()];
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
        $variants = ProductVariant::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($variants as $variant) {
            $this->restore($variant);
            $restored[] = $variant->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + sku + price + is_active.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = ProductVariant::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $variant = $byId[$change['id']] ?? null;
                if (!$variant) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'sku', 'price', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $variant->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $variant->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
