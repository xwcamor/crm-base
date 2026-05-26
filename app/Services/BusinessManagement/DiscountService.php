<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Discounts\BulkDiscountsActionJob;
use App\Models\AuditLog;
use App\Models\Discount;
use Illuminate\Support\Facades\DB;

/**
 * DiscountService — operaciones de negocio del modulo Discounts.
 *
 * Clon del patron de WarehouseService/CustomerService: el controller queda
 * thin y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class DiscountService
{
    public function create(array $data): Discount
    {
        $discount = new Discount($data);
        $discount->created_by = auth()->id();
        $discount->save();
        return $discount;
    }

    public function update(Discount $discount, array $data): Discount
    {
        $discount->update($data);
        return $discount;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Discount $discount, string $reason): void
    {
        $discount->deleted_description = $reason;
        $discount->deleted_by          = auth()->id();
        $discount->is_active           = false;
        $discount->saveQuietly();
        $discount->delete();
    }

    public function restore(Discount $discount): Discount
    {
        $discount->deleted_description = null;
        $discount->deleted_by          = null;
        $discount->restore();
        return $discount;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Discount $discount, string $reason): void
    {
        DB::transaction(function () use ($discount, $reason) {
            $locked = Discount::onlyTrashed()->where('id', $discount->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Discount {$discount->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Discount::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'code' => $locked->code,
                    'name' => $locked->name,
                    'slug' => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'discounts',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el discount. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El `code` no se copia (es unique por tenant — se deja null para que el
     * usuario lo ajuste al editar el clon).
     */
    public function duplicate(Discount $discount): ?Discount
    {
        $base    = $discount->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($discount, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Discount::query()
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
                'description', 'type', 'value', 'currency_code',
                'min_purchase_amount', 'usage_limit', 'usage_per_customer',
                'valid_from', 'valid_until',
                'is_active',
            ];
            $clone = new Discount($discount->only($cloneAttrs));
            $clone->name        = $candidate;
            $clone->code        = $this->generateUniqueCode($discount->code);
            $clone->usage_count = 0;
            $clone->created_by  = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    /**
     * Genera un código único per-tenant añadiendo sufijo "-COPY-N" al original.
     */
    protected function generateUniqueCode(string $base): string
    {
        $tenantId = auth()->user()?->tenant_id;
        $candidate = substr($base, 0, 50) . '-COPY';
        $i = 1;
        while (
            DB::table('discounts')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('code', $candidate)
                ->exists()
        ) {
            $i++;
            $candidate = substr($base, 0, 48) . '-COPY-' . $i;
            if ($i > 100) {
                $candidate = substr($base, 0, 48) . '-' . substr(\Illuminate\Support\Str::random(8), 0, 8);
                break;
            }
        }
        return $candidate;
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkDiscountsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkDiscountsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDiscountsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $discounts  = Discount::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($discounts as $discount) {
            $this->delete($discount, $reason);
            $deletedIds[] = $discount->id;
        }
        return ['queued' => false, 'count' => $discounts->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDiscountsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $discounts = Discount::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($discounts as $discount) {
            if ((bool) $discount->is_active === $isActive) continue;
            $discount->update(['is_active' => $isActive]);
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
            BulkDiscountsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $discounts = Discount::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($discounts as $discount) {
            $this->restore($discount);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $discounts->count()];
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
        $discounts = Discount::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($discounts as $discount) {
            $this->restore($discount);
            $restored[] = $discount->id;
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
            $byId = Discount::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $discount = $byId[$change['id']] ?? null;
                if (!$discount) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $discount->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $discount->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
