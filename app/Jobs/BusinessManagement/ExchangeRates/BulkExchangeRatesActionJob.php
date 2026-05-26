<?php

namespace App\Jobs\BusinessManagement\ExchangeRates;

use App\Models\ExchangeRate;
use App\Services\BusinessManagement\ExchangeRateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bulk operations en background cuando count > asyncThreshold().
 * Actions: 'delete' | 'set_active' | 'restore'.
 *
 * Clon del patron de BulkDiscountsActionJob.
 */
class BulkExchangeRatesActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

    /**
     * Umbral configurable. Prioridad: Setting global -> config/exchange_rates.php -> 200.
     */
    public static function asyncThreshold(): int
    {
        return \App\Models\Setting::getInt(
            'bulk.async_threshold',
            (int) config('exchange_rates.bulk_async_threshold', 200),
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

    public function handle(ExchangeRateService $service): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            \Log::warning('BulkExchangeRatesActionJob: user not found, aborting', [
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
            $rates = match ($this->action) {
                'restore' => ExchangeRate::onlyTrashed()->whereIn('id', $chunk)->get(),
                default   => ExchangeRate::whereIn('id', $chunk)->get(),
            };

            foreach ($rates as $rate) {
                try {
                    match ($this->action) {
                        'delete'     => $service->delete($rate, $this->payload['reason'] ?? 'Bulk delete'),
                        'set_active' => $this->setActive($service, $rate),
                        'restore'    => $service->restore($rate),
                        default      => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
                    };
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::warning("BulkExchangeRatesActionJob: error on exchange_rate {$rate->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        \Log::info("BulkExchangeRatesActionJob completed", [
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
        \Log::error("BulkExchangeRatesActionJob failed", [
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
            \Log::warning('BulkExchangeRatesActionJob: notify failed', ['error' => $e->getMessage()]);
        }
    }

    protected function setActive(ExchangeRateService $service, ExchangeRate $rate): void
    {
        $target = (bool) ($this->payload['is_active'] ?? true);
        if ((bool) $rate->is_active === $target) return;
        $service->update($rate, ['is_active' => $target]);
    }
}
