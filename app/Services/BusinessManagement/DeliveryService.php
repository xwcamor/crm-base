<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\Deliveries\BulkDeliveriesActionJob;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * DeliveryService — operaciones de negocio del modulo Deliveries.
 *
 * Clon adaptado del patron SalesOrderService: el controller queda thin y delega
 * aqui toda la mutacion de datos. Diferencia clave vs SalesOrder: Delivery
 * tiene line-items (DeliveryItem) que descuentan stock de la SalesOrder.
 * El propagateFulfillment() actualiza quantity_fulfilled de los items de la SO
 * cuando la entrega esta en estado 'shipped' o 'delivered'.
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class DeliveryService
{
    /**
     * Crea una entrega con sus lineas y propaga fulfillment a la SO.
     */
    public function create(array $data): Delivery
    {
        return DB::transaction(function () use ($data) {
            $headerData = $this->extractHeaderPayload($data);
            $headerData['created_by'] = auth()->id();
            $headerData['tenant_id']  = auth()->user()?->tenant_id;
            $headerData['prefix']     = $headerData['prefix'] ?? 'DEL';

            $delivery = Delivery::create($headerData);
            $this->syncItems($delivery, $data['items'] ?? []);
            $this->propagateFulfillment($delivery);
            return $delivery->fresh('items');
        });
    }

    public function update(Delivery $delivery, array $data): Delivery
    {
        return DB::transaction(function () use ($delivery, $data) {
            $headerData = $this->extractHeaderPayload($data);
            $delivery->update($headerData);

            // Si el caller mando items (form completo) -> resync. Si NO los
            // mando (ej. bulk status update) -> conservar las lineas actuales.
            if (array_key_exists('items', $data)) {
                $this->syncItems($delivery, $data['items'] ?? []);
            }
            $this->propagateFulfillment($delivery);
            return $delivery->fresh('items');
        });
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Delivery $delivery, string $reason): void
    {
        $delivery->deleted_description = $reason;
        $delivery->deleted_by          = auth()->id();
        $delivery->saveQuietly();
        $delivery->delete();

        // Al borrar la entrega, recalcular fulfillment de la SO de origen.
        if ($delivery->sales_order_id) {
            $this->recomputeOrderFulfillment($delivery->sales_order_id);
        }
    }

    public function restore(Delivery $delivery): Delivery
    {
        $delivery->deleted_description = null;
        $delivery->deleted_by          = null;
        $delivery->restore();

        if ($delivery->sales_order_id) {
            $this->recomputeOrderFulfillment($delivery->sales_order_id);
        }

        return $delivery;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Delivery $delivery, string $reason): void
    {
        DB::transaction(function () use ($delivery, $reason) {
            $locked = Delivery::onlyTrashed()->where('id', $delivery->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Delivery {$delivery->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Delivery::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'reference'       => $locked->reference,
                    'sales_order_id'  => $locked->sales_order_id,
                    'warehouse_id'    => $locked->warehouse_id,
                    'status'          => $locked->status,
                    'tracking_number' => $locked->tracking_number,
                    'slug'            => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'deliveries',
                'created_at'     => now(),
            ]);

            // Cascadea las lineas antes del forceDelete (la migration ya tiene
            // ON DELETE CASCADE; defense in depth).
            DeliveryItem::where('delivery_id', $locked->id)->delete();
            $locked->forceDelete();
        });
    }

    /**
     * Clona la entrega (header + lineas). Reference recibe sufijo "(copia)"
     * con sanity guard de 100 intentos. El clon arranca en status 'pending'
     * y sin shipped_at/delivered_at.
     */
    public function duplicate(Delivery $delivery): ?Delivery
    {
        $base    = $delivery->reference . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($delivery, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Delivery::query()
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

            $delivery->loadMissing('items');

            $clone = new Delivery($delivery->only([
                'prefix', 'sales_order_id', 'warehouse_id',
                'expected_delivery_date',
                'carrier', 'shipping_method', 'shipping_cost',
                'shipping_address',
            ]));
            $clone->reference      = $candidate;
            $clone->status         = 'pending';
            $clone->shipped_at     = null;
            $clone->delivered_at   = null;
            $clone->signed_by_name = null;
            $clone->tracking_number = null;
            $clone->notes          = null;
            $clone->created_by     = auth()->id();
            $clone->save();

            // Clonar lineas (cantidades preservadas; el caller decidira si
            // ajustar antes de despachar).
            foreach ($delivery->items as $item) {
                DeliveryItem::create([
                    'delivery_id'         => $clone->id,
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'product_id'          => $item->product_id,
                    'variant_id'          => $item->variant_id ?? null,
                    'stock_lot_id'        => $item->stock_lot_id ?? null,
                    'quantity'            => $item->quantity,
                ]);
            }

            // El clon arranca en pending: no recomputar fulfillment hasta que
            // alguien lo marque shipped/delivered.

            return $clone->fresh('items');
        });
    }

    // --- Bulk ops -----------------------------------------------------------

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkDeliveriesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDeliveriesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $deliveries = Delivery::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($deliveries as $delivery) {
            $this->delete($delivery, $reason);
            $deletedIds[] = $delivery->id;
        }
        return ['queued' => false, 'count' => $deliveries->count(), 'deleted' => $deletedIds];
    }

    /**
     * Bulk update de status. La ruta se llama `bulk_set_active` por consistencia
     * con el resto de los modulos (Customer/Product), pero aqui el target NO
     * es un boolean sino un status enum.
     *
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, string $status): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkDeliveriesActionJob::dispatch(
                (int) auth()->id(),
                'set_status',
                $ids,
                ['status' => $status],
            );
            return ['queued' => true, 'count' => $count];
        }

        $deliveries = Delivery::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($deliveries as $delivery) {
            if ((string) $delivery->status === $status) continue;
            $patch = ['status' => $status];
            // Si pasa a shipped y no tenia fecha, marcarla.
            if ($status === 'shipped' && $delivery->shipped_at === null) {
                $patch['shipped_at'] = now();
            }
            if ($status === 'delivered' && $delivery->delivered_at === null) {
                $patch['delivered_at'] = now();
            }
            $delivery->update($patch);
            if ($delivery->sales_order_id) {
                $this->recomputeOrderFulfillment($delivery->sales_order_id);
            }
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
            BulkDeliveriesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $deliveries = Delivery::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($deliveries as $delivery) {
            $this->restore($delivery);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $deliveries->count()];
    }

    /**
     * Undo dentro del window de 60s. Solo restaura filas con deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $deliveries = Delivery::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($deliveries as $delivery) {
            $this->restore($delivery);
            $restored[] = $delivery->id;
        }
        return $restored;
    }

    /**
     * Batch update de reference + status + carrier. Persistencia en
     * transaccion para atomicidad. Skip filas sin cambio real para evitar
     * audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;
        $allowedKeys = ['reference', 'status', 'carrier'];
        $affectedOrders = [];

        DB::transaction(function () use ($changes, &$touched, &$affectedOrders, $allowedKeys) {
            $ids  = array_column($changes, 'id');
            $byId = Delivery::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $delivery = $byId[$change['id']] ?? null;
                if (!$delivery) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip($allowedKeys)),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $delivery->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                // Si cambia status a shipped/delivered y no tiene fecha, marcarla.
                if (isset($patch['status'])) {
                    if ($patch['status'] === 'shipped' && $delivery->shipped_at === null) {
                        $patch['shipped_at'] = now();
                    }
                    if ($patch['status'] === 'delivered' && $delivery->delivered_at === null) {
                        $patch['delivered_at'] = now();
                    }
                }

                $delivery->fill($patch)->save();
                if ($delivery->sales_order_id) {
                    $affectedOrders[$delivery->sales_order_id] = true;
                }
                $touched++;
            }
        });

        foreach (array_keys($affectedOrders) as $orderId) {
            $this->recomputeOrderFulfillment((int) $orderId);
        }

        return $touched;
    }

    // --- Helpers (lineas + fulfillment) -------------------------------------

    /** Extrae solo los campos del header (sin items). */
    protected function extractHeaderPayload(array $data): array
    {
        $keys = [
            'reference', 'prefix',
            'sales_order_id', 'warehouse_id',
            'status',
            'expected_delivery_date', 'shipped_at', 'delivered_at', 'signed_by_name',
            'carrier', 'tracking_number', 'shipping_method', 'shipping_cost',
            'shipping_address',
            'notes',
        ];
        return collect($data)->only($keys)->toArray();
    }

    /**
     * Reemplaza las lineas de la entrega con las del array. Idempotente:
     * delete-all + insert. Skip lineas con quantity <= 0.
     */
    public function syncItems(Delivery $delivery, array $items): void
    {
        DeliveryItem::where('delivery_id', $delivery->id)->delete();
        foreach ($items as $it) {
            $qty = (float) ($it['quantity'] ?? 0);
            if ($qty <= 0) continue;
            DeliveryItem::create([
                'delivery_id'         => $delivery->id,
                'sales_order_item_id' => $it['sales_order_item_id'],
                'product_id'          => $it['product_id'],
                'variant_id'          => $it['variant_id'] ?? null,
                'stock_lot_id'        => $it['stock_lot_id'] ?? null,
                'quantity'            => $qty,
            ]);
        }
    }

    /**
     * Propaga el fulfillment de la entrega a la SO de origen. Suma todas las
     * entregas en estado 'shipped' o 'delivered' por sales_order_item_id y
     * actualiza quantity_fulfilled. Si todo entregado -> SO 'delivered'.
     * Si algo entregado -> SO 'partially_shipped'.
     */
    public function propagateFulfillment(Delivery $delivery): void
    {
        if (!$delivery->sales_order_id) return;
        $this->recomputeOrderFulfillment($delivery->sales_order_id);
    }

    protected function recomputeOrderFulfillment(int $salesOrderId): void
    {
        $order = SalesOrder::with('items')->find($salesOrderId);
        if (!$order) return;

        foreach ($order->items as $item) {
            $totalDelivered = (float) DeliveryItem::where('sales_order_item_id', $item->id)
                ->whereHas('delivery', fn ($q) => $q->whereIn('status', ['shipped', 'delivered']))
                ->sum('quantity');
            $item->update(['quantity_fulfilled' => $totalDelivered]);
        }

        $allFulfilled = $order->items->count() > 0
            && $order->items->every(fn ($i) => (float) $i->quantity_fulfilled >= (float) $i->quantity_ordered);
        $anyFulfilled = $order->items->contains(fn ($i) => (float) $i->quantity_fulfilled > 0);

        if ($allFulfilled) {
            $order->update(['status' => 'delivered', 'delivered_at' => $order->delivered_at ?? now()]);
        } elseif ($anyFulfilled && in_array($order->status, ['pending', 'processing'])) {
            $order->update(['status' => 'partially_shipped']);
        }
    }
}
