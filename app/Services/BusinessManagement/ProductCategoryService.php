<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\ProductCategories\BulkProductCategoriesActionJob;
use App\Models\AuditLog;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

/**
 * ProductCategoryService — operaciones de negocio del modulo Product Categories.
 *
 * Clon del patron de DiscountService/CustomerService: controller thin que
 * delega aqui toda la mutacion de datos. Auditable trait dispara los logs
 * en created/updated/deleted/restored; force_delete escribe el audit manual.
 */
class ProductCategoryService
{
    public function create(array $data): ProductCategory
    {
        $category = new ProductCategory($data);
        $category->created_by = auth()->id();
        $category->save();
        return $category;
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);
        return $category;
    }

    public function delete(ProductCategory $category, string $reason): void
    {
        $category->deleted_description = $reason;
        $category->deleted_by          = auth()->id();
        $category->is_active           = false;
        $category->saveQuietly();
        $category->delete();
    }

    public function restore(ProductCategory $category): ProductCategory
    {
        $category->deleted_description = null;
        $category->deleted_by          = null;
        $category->restore();
        return $category;
    }

    public function forceDelete(ProductCategory $category, string $reason): void
    {
        DB::transaction(function () use ($category, $reason) {
            $locked = ProductCategory::onlyTrashed()->where('id', $category->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("ProductCategory {$category->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => ProductCategory::class,
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
                'module'         => 'product_categories',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona la categoria. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(ProductCategory $category): ?ProductCategory
    {
        $base    = $category->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($category, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $q = ProductCategory::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(name) = LOWER(?)', [$candidate]),
                    );
                if ($category->parent_id) {
                    $q->where('parent_id', $category->parent_id);
                } else {
                    $q->whereNull('parent_id');
                }
                $exists = $q->lockForUpdate()->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'description', 'parent_id', 'sort_order', 'is_active',
            ];
            $clone = new ProductCategory($category->only($cloneAttrs));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkProductCategoriesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductCategoriesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $categories = ProductCategory::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($categories as $category) {
            $this->delete($category, $reason);
            $deletedIds[] = $category->id;
        }
        return ['queued' => false, 'count' => $categories->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkProductCategoriesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $categories = ProductCategory::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($categories as $category) {
            if ((bool) $category->is_active === $isActive) continue;
            $category->update(['is_active' => $isActive]);
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
            BulkProductCategoriesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $categories = ProductCategory::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($categories as $category) {
            $this->restore($category);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $categories->count()];
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
        $categories = ProductCategory::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($categories as $category) {
            $this->restore($category);
            $restored[] = $category->id;
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
            $byId = ProductCategory::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $category = $byId[$change['id']] ?? null;
                if (!$category) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $category->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $category->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
