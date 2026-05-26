<?php

namespace App\Services\BusinessManagement;

use App\Jobs\BusinessManagement\PurchaseOrders\BulkPurchaseOrdersActionJob;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PurchaseOrderService — operaciones de negocio del modulo PurchaseOrders.
 *
 * Clon del patron de CustomerService: el controller queda thin y delega aca
 * toda la mutacion de datos. Mantiene los audit logs cerca de la operacion
 * (Auditable trait dispara en created/updated/deleted/restored; force_delete
 * escribe el audit manual).
 *
 * Diferencia con CustomerService: PurchaseOrder tiene `items` (lineas) que
 * recompute totales, y `status` en lugar de `is_active`.
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class PurchaseOrderService
{
    /**
     * Crea la OC + sus items + recompute totales en una transaccion.
     *
     * @param array{header: array, items: array<int, array>} $data
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $header = $data['header'] ?? $data;
            $header['created_by'] = auth()->id();
            if (empty($header['prefix'])) {
                $header['prefix'] = 'PO';
            }
            $order = new PurchaseOrder($header);
            $order->save();

            $this->syncItems($order, $data['items'] ?? []);
            $this->recomputeTotals($order);

            return $order->fresh();
        });
    }

    /**
     * Actualiza la OC + replace de items + recompute totales.
     *
     * @param array{header: array, items: array<int, array>} $data
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $header = $data['header'] ?? $data;
            $order->update($header);

            $this->syncItems($order, $data['items'] ?? []);
            $this->recomputeTotals($order);

            return $order->fresh();
        });
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(PurchaseOrder $order, string $reason): void
    {
        $order->deleted_description = $reason;
        $order->deleted_by          = auth()->id();
        $order->saveQuietly();
        $order->delete();
    }

    public function restore(PurchaseOrder $order): PurchaseOrder
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
    public function forceDelete(PurchaseOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $locked = PurchaseOrder::onlyTrashed()->where('id', $order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("PurchaseOrder {$order->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => PurchaseOrder::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'reference' => $locked->reference,
                    'status'    => $locked->status,
                    'slug'      => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'purchase_orders',
                'created_at'     => now(),
            ]);

            // Borrar items primero (FK constraint). El propio cascade del schema
            // tambien lo hace, pero lo hacemos explicito para auditoria clara.
            PurchaseOrderItem::where('purchase_order_id', $locked->id)->delete();
            $locked->forceDelete();
        });
    }

    /**
     * Clona la OC. Reference se regenera (correlativo); items se copian;
     * status fuerza a 'draft' (no copies estados confirmados/recibidos).
     */
    public function duplicate(PurchaseOrder $order): ?PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order->load('items');

            $clone = new PurchaseOrder($order->only([
                'supplier_company_id', 'warehouse_id', 'owner_id',
                'currency_code', 'payment_terms_days', 'delivery_type',
                'subtotal', 'tax_total', 'discount_total', 'shipping_cost', 'grand_total',
                'terms_md', 'notes',
                'tenant_id',
            ]));
            $clone->status     = 'draft';
            $clone->order_date = now()->toDateString();
            $clone->prefix     = 'PO';
            $clone->reference  = $this->nextReference($order->tenant_id);
            $clone->created_by = auth()->id();
            $clone->save();

            foreach ($order->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $clone->id,
                    'product_id'        => $item->product_id,
                    'name'              => $item->name,
                    'description'       => $item->description,
                    'quantity_ordered'  => $item->quantity_ordered,
                    'quantity_received' => 0,
                    'unit_cost'         => $item->unit_cost,
                    'discount_pct'      => $item->discount_pct,
                    'tax_pct'           => $item->tax_pct,
                    'line_subtotal'     => $item->line_subtotal,
                    'line_tax'          => $item->line_tax,
                    'line_total'        => $item->line_total,
                    'sort_order'        => $item->sort_order,
                ]);
            }

            return $clone->fresh();
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline.

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPurchaseOrdersActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPurchaseOrdersActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $orders     = PurchaseOrder::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($orders as $order) {
            $this->delete($order, $reason);
            $deletedIds[] = $order->id;
        }
        return ['queued' => false, 'count' => $orders->count(), 'deleted' => $deletedIds];
    }

    /**
     * Bulk transicion de status. Equivalente semantico al bulkSetActive de
     * Customer — PurchaseOrder no tiene `is_active`, su "estado" es `status`.
     *
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetStatus(array $ids, string $status): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPurchaseOrdersActionJob::dispatch(
                (int) auth()->id(),
                'set_status',
                $ids,
                ['status' => $status],
            );
            return ['queued' => true, 'count' => $count];
        }

        $orders  = PurchaseOrder::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($orders as $order) {
            if ($order->status === $status) continue;
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
            BulkPurchaseOrdersActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $orders = PurchaseOrder::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($orders as $order) {
            $this->restore($order);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $orders->count()];
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
        $orders = PurchaseOrder::onlyTrashed()
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
     * Batch update de reference + status. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = PurchaseOrder::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $order = $byId[$change['id']] ?? null;
                if (!$order) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['reference', 'status'])),
                    fn ($v) => $v !== null && $v !== '',
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

    // ─── Helpers internos ──────────────────────────────────────────────────

    public function nextReference(?int $tenantId): string
    {
        $year  = Carbon::now()->year;
        $count = PurchaseOrder::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereYear('created_at', $year)
            ->count() + 1;
        return sprintf('PO-%d-%04d', $year, $count);
    }

    protected function syncItems(PurchaseOrder $order, array $items): void
    {
        // Replace strategy: borrar todos los items y recrearlos. Mas simple
        // que diff por id; los items no se referencian externamente.
        PurchaseOrderItem::where('purchase_order_id', $order->id)->delete();

        foreach ($items as $idx => $it) {
            $qty  = (float) $it['quantity'];
            $unit = (float) $it['unit_price'];
            $disc = (float) ($it['discount_pct'] ?? 0);
            $tax  = (float) ($it['tax_pct'] ?? 0);
            $sub  = round($qty * $unit * (1 - $disc / 100), 2);
            $taxA = round($sub * $tax / 100, 2);

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id'        => $it['product_id'] ?? null,
                'name'              => $it['name'],
                'description'       => $it['description'] ?? null,
                'quantity_ordered'  => $qty,
                'quantity_received' => 0,
                'unit_cost'         => $unit,
                'discount_pct'      => $disc,
                'tax_pct'           => $tax,
                'line_subtotal'     => $sub,
                'line_tax'          => $taxA,
                'line_total'        => round($sub + $taxA, 2),
                'sort_order'        => $idx,
            ]);
        }
    }

    protected function recomputeTotals(PurchaseOrder $order): void
    {
        $totals = PurchaseOrderItem::where('purchase_order_id', $order->id)
            ->selectRaw('SUM(line_subtotal) as subtotal, SUM(line_tax) as tax_total, SUM(line_total) as grand_total')
            ->first();

        $order->update([
            'subtotal'    => $totals->subtotal    ?? 0,
            'tax_total'   => $totals->tax_total   ?? 0,
            'grand_total' => $totals->grand_total ?? 0,
        ]);
    }
}
