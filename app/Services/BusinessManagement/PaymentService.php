<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Payments\BulkPaymentsActionJob;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService — operaciones de negocio del modulo Payments.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class PaymentService
{
    public function create(array $data): Payment
    {
        $payment = new Payment($data);
        $payment->created_by = auth()->id();
        $payment->save();
        return $payment;
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Payment $payment, string $reason): void
    {
        $payment->deleted_description = $reason;
        $payment->deleted_by          = auth()->id();
        $payment->is_active           = false;
        $payment->saveQuietly();
        $payment->delete();
    }

    public function restore(Payment $payment): Payment
    {
        $payment->deleted_description = null;
        $payment->deleted_by          = null;
        $payment->restore();
        return $payment;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::onlyTrashed()->where('id', $payment->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Payment {$payment->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Payment::class,
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
                'module'         => 'payments',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Payment $payment): ?Payment
    {
        $base    = $payment->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($payment, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Payment::query()
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
            $clone = new Payment($payment->only($cloneAttrs));
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
    // BulkPaymentsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPaymentsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPaymentsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $payments  = Payment::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($payments as $payment) {
            $this->delete($payment, $reason);
            $deletedIds[] = $payment->id;
        }
        return ['queued' => false, 'count' => $payments->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPaymentsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $payments = Payment::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($payments as $payment) {
            if ((bool) $payment->is_active === $isActive) continue;
            $payment->update(['is_active' => $isActive]);
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
            BulkPaymentsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $payments = Payment::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($payments as $payment) {
            $this->restore($payment);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $payments->count()];
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
        $payments = Payment::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($payments as $payment) {
            $this->restore($payment);
            $restored[] = $payment->id;
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
            $byId  = Payment::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $payment = $byId[$change['id']] ?? null;
                if (!$payment) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $payment->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $payment->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
