<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\PaymentMethods\BulkPaymentMethodsActionJob;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

/**
 * PaymentMethodService — operaciones de negocio del modulo Payment Methods.
 *
 * Clon del patron de ProductCategoryService/DiscountService: controller thin
 * que delega aqui toda la mutacion de datos. Auditable trait dispara los logs
 * en created/updated/deleted/restored; force_delete escribe el audit manual.
 */
class PaymentMethodService
{
    public function create(array $data): PaymentMethod
    {
        $method = new PaymentMethod($data);
        $method->created_by = auth()->id();
        $method->save();
        return $method;
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        $method->update($data);
        return $method;
    }

    public function delete(PaymentMethod $method, string $reason): void
    {
        $method->deleted_description = $reason;
        $method->deleted_by          = auth()->id();
        $method->is_active           = false;
        $method->saveQuietly();
        $method->delete();
    }

    public function restore(PaymentMethod $method): PaymentMethod
    {
        $method->deleted_description = null;
        $method->deleted_by          = null;
        $method->restore();
        return $method;
    }

    public function forceDelete(PaymentMethod $method, string $reason): void
    {
        DB::transaction(function () use ($method, $reason) {
            $locked = PaymentMethod::onlyTrashed()->where('id', $method->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("PaymentMethod {$method->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => PaymentMethod::class,
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
                'module'         => 'payment_methods',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el metodo de pago. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(PaymentMethod $method): ?PaymentMethod
    {
        $base    = $method->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($method, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $q = PaymentMethod::query()
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
                'code', 'description', 'integration_provider',
                'requires_reference', 'sort_order', 'is_active',
            ];
            $clone = new PaymentMethod($method->only($cloneAttrs));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPaymentMethodsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPaymentMethodsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $methods = PaymentMethod::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($methods as $method) {
            $this->delete($method, $reason);
            $deletedIds[] = $method->id;
        }
        return ['queued' => false, 'count' => $methods->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPaymentMethodsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $methods = PaymentMethod::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($methods as $method) {
            if ((bool) $method->is_active === $isActive) continue;
            $method->update(['is_active' => $isActive]);
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
            BulkPaymentMethodsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $methods = PaymentMethod::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($methods as $method) {
            $this->restore($method);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $methods->count()];
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
        $methods = PaymentMethod::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($methods as $method) {
            $this->restore($method);
            $restored[] = $method->id;
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
            $byId = PaymentMethod::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $method = $byId[$change['id']] ?? null;
                if (!$method) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $method->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $method->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
