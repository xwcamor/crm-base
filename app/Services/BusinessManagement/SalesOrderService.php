<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\SalesOrders\BulkSalesOrdersActionJob;
use App\Models\AuditLog;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * SalesOrderService — operaciones de negocio del modulo Sales Orders.
 *
 * Clon adaptado del patron CustomerService: el controller queda thin y delega
 * aca toda la mutacion de datos. Diferencia clave vs Customer: SalesOrder
 * tiene line-items (SalesOrderItem) que se sincronizan en create/update/
 * duplicate. El recompute de totales sucede en el mismo flow.
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class SalesOrderService
{
    /**
     * Crea una OV con sus lineas y recomputa totales.
     */
    public function create(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->extractHeaderPayload($data, isUpdate: false);
            $headerData['created_by'] = auth()->id();
            $headerData['tenant_id']  = auth()->user()?->tenant_id;
            $headerData['prefix']     = $headerData['prefix'] ?? 'OV';

            $order = SalesOrder::create($headerData);
            $this->syncItems($order, $data['items'] ?? []);
            $this->recomputeTotals($order);
            return $order->fresh('items');
        });
    }

    public function update(SalesOrder $order, array $data): SalesOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $headerData = $this->extractHeaderPayload($data, isUpdate: true);
            $order->update($headerData);

            // Si el caller mando items (form completo) → resync. Si NO los
            // mando (ej. bulk status update) → conservar las lineas actuales.
            if (array_key_exists('items', $data)) {
                $this->syncItems($order, $data['items'] ?? []);
                $this->recomputeTotals($order);
            }
            return $order->fresh('items');
        });
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`. A diferencia de Customer, no
     * tocamos `is_active` (SalesOrder no lo tiene) — el status se preserva.
     */
    public function delete(SalesOrder $order, string $reason): void
    {
        $order->deleted_description = $reason;
        $order->deleted_by          = auth()->id();
        $order->saveQuietly();
        $order->delete();
    }

    public function restore(SalesOrder $order): SalesOrder
    {
        $order->deleted_description = null;
        $order->deleted_by          = null;
        $order->restore();
        return $order;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(SalesOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $locked = SalesOrder::onlyTrashed()->where('id', $order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("SalesOrder {$order->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => SalesOrder::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'reference'   => $locked->reference,
                    'company_id'  => $locked->company_id,
                    'grand_total' => $locked->grand_total,
                    'status'      => $locked->status,
                    'slug'        => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'sales_orders',
                'created_at'     => now(),
            ]);

            // Cascadea las lineas antes del forceDelete (no hay FK ON DELETE
            // CASCADE garantizado en la migration — defense in depth).
            SalesOrderItem::where('sales_order_id', $locked->id)->delete();
            $locked->forceDelete();
        });
    }

    /**
     * Clona la OV (header + lineas). Reference recibe sufijo "(copia)" con
     * sanity guard de 100 intentos. El clon arranca en status 'pending' y
     * payment_status 'unpaid', cualquiera fuese el estado del original.
     */
    public function duplicate(SalesOrder $order): ?SalesOrder
    {
        $base    = $order->reference . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($order, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = SalesOrder::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(reference)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(reference) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $order->loadMissing('items');

            $clone = new SalesOrder($order->only([
                'prefix', 'external_reference',
                'company_id', 'contact_id', 'warehouse_id',
                'currency_code', 'payment_terms_days',
                'shipping_address', 'billing_address',
                'notes', 'internal_notes', 'owner_id',
            ]));
            $clone->reference      = $candidate;
            $clone->status         = 'pending';
            $clone->payment_status = 'unpaid';
            $clone->order_date     = now()->toDateString();
            $clone->subtotal       = 0;
            $clone->discount_total = 0;
            $clone->tax_total      = 0;
            $clone->shipping_cost  = $order->shipping_cost ?? 0;
            $clone->grand_total    = 0;
            $clone->created_by     = auth()->id();
            $clone->save();

            // Clonar lineas
            foreach ($order->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id'     => $clone->id,
                    'product_id'         => $item->product_id,
                    'name'               => $item->name,
                    'sku'                => $item->sku,
                    'quantity_ordered'   => $item->quantity_ordered,
                    'quantity_fulfilled' => 0,
                    'unit_price'         => $item->unit_price,
                    'discount_pct'       => $item->discount_pct,
                    'tax_pct'            => $item->tax_pct,
                    'line_subtotal'      => $item->line_subtotal,
                    'line_tax'           => $item->line_tax,
                    'line_total'         => $item->line_total,
                    'sort_order'         => $item->sort_order,
                ]);
            }
            $this->recomputeTotals($clone);

            return $clone->fresh('items');
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkSalesOrdersActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkSalesOrdersActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $orders     = SalesOrder::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($orders as $order) {
            $this->delete($order, $reason);
            $deletedIds[] = $order->id;
        }
        return ['queued' => false, 'count' => $orders->count(), 'deleted' => $deletedIds];
    }

    /**
     * Bulk update de status. A diferencia de Customer (boolean is_active),
     * SalesOrder usa enum `status`. Mantenemos el mismo nombre `bulkSetActive`
     * por consistencia con el patron — el payload pasa el status objetivo.
     *
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, string $status): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkSalesOrdersActionJob::dispatch(
                (int) auth()->id(),
                'set_status',
                $ids,
                ['status' => $status],
            );
            return ['queued' => true, 'count' => $count];
        }

        $orders  = SalesOrder::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($orders as $order) {
            if ((string) $order->status === $status) continue;
            $order->update(['status' => $status]);
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
            BulkSalesOrdersActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $orders = SalesOrder::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $this->restore($order);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $orders->count()];
    }

    /**
     * Undo dentro del window de 60s. Solo restaura filas con deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $orders = SalesOrder::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($orders as $order) {
            $this->restore($order);
            $restored[] = $order->id;
        }
        return $restored;
    }

    /**
     * Batch update de reference + status + payment_status. Persistencia en
     * transaccion para atomicidad. Skip filas sin cambio real para evitar
     * audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;
        $allowedKeys = ['reference', 'status', 'payment_status'];

        DB::transaction(function () use ($changes, &$touched, $allowedKeys) {
            $ids   = array_column($changes, 'id');
            $byId  = SalesOrder::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $order = $byId[$change['id']] ?? null;
                if (!$order) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip($allowedKeys)),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $order->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $order->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }

    // ─── Helpers (lineas + totales) ────────────────────────────────────────

    /** Extrae solo los campos del header (sin items). */
    protected function extractHeaderPayload(array $data, bool $isUpdate): array
    {
        $keys = [
            'reference', 'prefix', 'external_reference',
            'quote_id', 'deal_id', 'company_id', 'contact_id',
            'status', 'warehouse_id',
            'order_date', 'expected_delivery_date',
            'currency_code',
            'shipping_cost',
            'payment_terms_days', 'payment_status',
            'shipping_address', 'billing_address',
            'notes', 'internal_notes',
            'owner_id',
        ];
        return collect($data)->only($keys)->toArray();
    }

    /**
     * Reemplaza las lineas de la OV con las del array. Idempotente: delete-
     * all + insert. Recomputa cada linea desde qty/unit/disc/tax.
     */
    public function syncItems(SalesOrder $order, array $items): void
    {
        SalesOrderItem::where('sales_order_id', $order->id)->delete();
        foreach ($items as $idx => $it) {
            $qty  = (float) $it['quantity'];
            $unit = (float) $it['unit_price'];
            $disc = (float) ($it['discount_pct'] ?? 0);
            $tax  = (float) ($it['tax_pct'] ?? 0);
            $sub  = round($qty * $unit * (1 - $disc / 100), 2);
            $taxA = round($sub * $tax / 100, 2);
            SalesOrderItem::create([
                'sales_order_id'     => $order->id,
                'product_id'         => $it['product_id'] ?? null,
                'name'               => $it['name'],
                'sku'                => $it['sku'] ?? null,
                'quantity_ordered'   => $qty,
                'quantity_fulfilled' => 0,
                'unit_price'         => $unit,
                'discount_pct'       => $disc,
                'tax_pct'            => $tax,
                'line_subtotal'      => $sub,
                'line_tax'           => $taxA,
                'line_total'         => round($sub + $taxA, 2),
                'sort_order'         => $idx,
            ]);
        }
    }

    public function recomputeTotals(SalesOrder $order): void
    {
        $totals = SalesOrderItem::where('sales_order_id', $order->id)
            ->selectRaw('SUM(line_subtotal) as subtotal, SUM(line_tax) as tax_total, SUM(line_total) as grand_total')
            ->first();
        $order->update([
            'subtotal'    => $totals->subtotal ?? 0,
            'tax_total'   => $totals->tax_total ?? 0,
            'grand_total' => $totals->grand_total ?? 0,
        ]);
    }
}
