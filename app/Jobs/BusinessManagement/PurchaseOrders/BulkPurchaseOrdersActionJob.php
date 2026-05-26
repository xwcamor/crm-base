<?php

namespace App\Jobs\BusinessManagement\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\BusinessManagement\PurchaseOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bulk operations en background cuando count > asyncThreshold().
 * Actions: 'delete' | 'set_status' | 'restore'.
 *
 * Clon del patron de BulkCustomersActionJob. `set_active` se reemplaza por
 * `set_status` (PurchaseOrder no tiene is_active boolean).
 */
class BulkPurchaseOrdersActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 3;

    public static function asyncThreshold(): int
    {
        return \App\Models\Setting::getInt(
            'bulk.async_threshold',
            (int) config('purchase_orders.bulk_async_threshold', 200),
        );
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    protected int $userId;
    protected string $action;
    protected array $ids;
    protected array $payload;

    public function __construct(int $userId, string $action, array $ids, array $payload = [])
    {
        $this->userId  = $userId;
        $this->action  = $action;
        $this->ids     = $ids;
        $this->payload = $payload;
    }

    public function handle(PurchaseOrderService $service): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            \Log::warning('BulkPurchaseOrdersActionJob: user not found, aborting', [
                'user_id' => $this->userId,
                'action'  => $this->action,
            ]);
            $this->fail(new \RuntimeException("User {$this->userId} not found"));
            return;
        }
        auth()->setUser($user);

        $processed = 0;
        $errors    = 0;

        foreach (array_chunk($this->ids, 200) as $chunk) {
            $orders = match ($this->action) {
                'restore' => PurchaseOrder::onlyTrashed()->whereIn('id', $chunk)->get(),
                default   => PurchaseOrder::whereIn('id', $chunk)->get(),
            };

            foreach ($orders as $order) {
                try {
                    match ($this->action) {
                        'delete'     => $service->delete($order, $this->payload['reason'] ?? 'Bulk delete'),
                        'set_status' => $this->setStatus($order),
                        'restore'    => $service->restore($order),
                        default      => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
                    };
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::warning("BulkPurchaseOrdersActionJob: error on purchase_order {$order->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        \Log::info("BulkPurchaseOrdersActionJob completed", [
            'user_id'   => $this->userId,
            'action'    => $this->action,
            'processed' => $processed,
            'errors'    => $errors,
            'total'     => count($this->ids),
        ]);

        $this->notifyUser('completed');
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("BulkPurchaseOrdersActionJob failed", [
            'user_id' => $this->userId,
            'action'  => $this->action,
            'total'   => count($this->ids),
            'error'   => $exception->getMessage(),
        ]);

        $this->notifyUser('failed', $exception->getMessage());
    }

    protected function notifyUser(string $status, ?string $error = null): void
    {
        try {
            \App\Models\Download::create([
                'slug'          => \Illuminate\Support\Str::random(22),
                'user_id'       => $this->userId,
                'type'          => 'task',
                'filename'      => "bulk_{$this->action}",
                'path'          => '',
                'disk'          => 'local',
                'status'        => $status === 'completed' ? 'ready' : 'failed',
                'error_message' => $error,
                'expires_at'    => \App\Models\Download::computeExpiresAt(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('BulkPurchaseOrdersActionJob: notify failed', ['error' => $e->getMessage()]);
        }
    }

    protected function setStatus(PurchaseOrder $order): void
    {
        $target = (string) ($this->payload['status'] ?? 'draft');
        if (!in_array($target, PurchaseOrder::STATUSES, true)) return;
        if ($order->status === $target) return;
        $order->update(['status' => $target]);
    }
}
