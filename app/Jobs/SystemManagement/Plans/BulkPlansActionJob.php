<?php

namespace App\Jobs\SystemManagement\Plans;

use App\Models\Plan;
use App\Services\SystemManagement\PlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bulk operations en background cuando count > asyncThreshold().
 * Actions: 'delete' | 'set_active' | 'restore'.
 *
 * Clon de BulkDiscountsActionJob — Plans super-only, sin tenant scoping.
 */
class BulkPlansActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

    public static function asyncThreshold(): int
    {
        return \App\Models\Setting::getInt(
            'bulk.async_threshold',
            (int) config('plans.bulk_async_threshold', 50),
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

    public function handle(PlanService $service): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            \Log::warning('BulkPlansActionJob: user not found, aborting', [
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
            $plans = match ($this->action) {
                'restore' => Plan::onlyTrashed()->whereIn('id', $chunk)->get(),
                default   => Plan::whereIn('id', $chunk)->get(),
            };

            foreach ($plans as $plan) {
                try {
                    match ($this->action) {
                        'delete' => $plan->tenantsCount() > 0
                            ? $errors++ // skip — bloqueado por dependents
                            : $service->delete($plan, $this->payload['reason'] ?? 'Bulk delete'),
                        'set_active' => $this->setActive($service, $plan),
                        'restore'    => $service->restore($plan),
                        default      => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
                    };
                    if ($this->action !== 'delete' || $plan->tenantsCount() === 0) {
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::warning("BulkPlansActionJob: error on plan {$plan->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        \Log::info("BulkPlansActionJob completed", [
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
        \Log::error("BulkPlansActionJob failed", [
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
            \Log::warning('BulkPlansActionJob: notify failed', ['error' => $e->getMessage()]);
        }
    }

    protected function setActive(PlanService $service, Plan $plan): void
    {
        $target = (bool) ($this->payload['is_active'] ?? true);
        if ((bool) $plan->is_active === $target) return;
        $service->update($plan, ['is_active' => $target]);
    }
}
