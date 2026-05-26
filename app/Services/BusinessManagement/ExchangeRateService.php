<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\ExchangeRates\BulkExchangeRatesActionJob;
use App\Models\AuditLog;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;

/**
 * ExchangeRateService — operaciones de negocio del modulo ExchangeRates.
 *
 * Clon del patron de DiscountService: el controller queda thin y delega
 * aqui toda la mutacion de datos. Audit logs disparan via Auditable trait;
 * force_delete escribe el audit manual antes del hard-delete.
 *
 * NO maneja exports/imports/list: esa orquestacion HTTP vive en el controller.
 */
class ExchangeRateService
{
    public function create(array $data): ExchangeRate
    {
        $rate = new ExchangeRate($data);
        $rate->created_by = auth()->id();
        if (empty($rate->source)) $rate->source = 'manual';
        $rate->save();
        return $rate;
    }

    public function update(ExchangeRate $rate, array $data): ExchangeRate
    {
        $rate->update($data);
        return $rate;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(ExchangeRate $rate, string $reason): void
    {
        $rate->deleted_description = $reason;
        $rate->deleted_by          = auth()->id();
        $rate->is_active           = false;
        $rate->saveQuietly();
        $rate->delete();
    }

    public function restore(ExchangeRate $rate): ExchangeRate
    {
        $rate->deleted_description = null;
        $rate->deleted_by          = null;
        $rate->restore();
        return $rate;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(ExchangeRate $rate, string $reason): void
    {
        DB::transaction(function () use ($rate, $reason) {
            $locked = ExchangeRate::onlyTrashed()->where('id', $rate->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("ExchangeRate {$rate->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => ExchangeRate::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'base_code'  => $locked->base_code,
                    'quote_code' => $locked->quote_code,
                    'rate'       => $locked->rate,
                    'valid_at'   => $locked->valid_at?->toIso8601String(),
                    'slug'       => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'exchange_rates',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Duplicate — clona la tasa avanzando valid_at +1 minuto (la unique key
     * incluye valid_at, asi que el clon necesita un timestamp distinto). Si
     * por colision se sigue chocando, suma minutos hasta encontrar slot libre.
     */
    public function duplicate(ExchangeRate $rate): ?ExchangeRate
    {
        return DB::transaction(function () use ($rate) {
            $tenantId = $rate->tenant_id;
            $base     = $rate->base_code;
            $quote    = $rate->quote_code;

            $candidate = $rate->valid_at?->copy()->addMinute() ?? now();
            $i = 0;

            while (true) {
                $exists = ExchangeRate::query()
                    ->withoutGlobalScopes()
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'))
                    ->where('base_code', $base)
                    ->where('quote_code', $quote)
                    ->where('valid_at', $candidate)
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $candidate->copy()->addMinute();
                $i++;
                if ($i > 100) return null;
            }

            $clone = new ExchangeRate([
                'base_code'  => $base,
                'quote_code' => $quote,
                'rate'       => $rate->rate,
                'valid_at'   => $candidate,
                'source'     => $rate->source,
                'is_active'  => (bool) $rate->is_active,
            ]);
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkExchangeRatesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkExchangeRatesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $rates      = ExchangeRate::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($rates as $rate) {
            $this->delete($rate, $reason);
            $deletedIds[] = $rate->id;
        }
        return ['queued' => false, 'count' => $rates->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkExchangeRatesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $rates   = ExchangeRate::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($rates as $rate) {
            if ((bool) $rate->is_active === $isActive) continue;
            $rate->update(['is_active' => $isActive]);
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
            BulkExchangeRatesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $rates = ExchangeRate::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($rates as $rate) {
            $this->restore($rate);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $rates->count()];
    }

    /**
     * Undo dentro del window de 60s. Defense in depth: solo restaura las filas
     * que matchean deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $rates = ExchangeRate::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($rates as $rate) {
            $this->restore($rate);
            $restored[] = $rate->id;
        }
        return $restored;
    }

    /**
     * Batch update de rate + is_active. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = ExchangeRate::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $rate = $byId[$change['id']] ?? null;
                if (!$rate) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['rate', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $rate->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $rate->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
