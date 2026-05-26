<?php

namespace App\Jobs\Communication\Messages;

use App\Models\Message;
use App\Services\Communication\MessageService;
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
class BulkMessagesActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 3;

    /**
     * Umbral configurable. Prioridad: Setting global -> config/messages.php -> 200.
     */
    public static function asyncThreshold(): int
    {
        return \App\Models\Setting::getInt(
            'bulk.async_threshold',
            (int) config('messages.bulk_async_threshold', 200),
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

    public function handle(MessageService $service): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            \Log::warning('BulkMessagesActionJob: user not found, aborting', [
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
            $messages = match ($this->action) {
                'restore' => Message::onlyTrashed()->whereIn('id', $chunk)->get(),
                default   => Message::whereIn('id', $chunk)->get(),
            };

            foreach ($messages as $message) {
                try {
                    match ($this->action) {
                        'delete'     => $service->delete($message, $this->payload['reason'] ?? 'Bulk delete'),
                        'set_active' => $this->setActive($service, $message),
                        'restore'    => $service->restore($message),
                        default      => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
                    };
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::warning("BulkMessagesActionJob: error on message {$message->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        \Log::info("BulkMessagesActionJob completed", [
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
        \Log::error("BulkMessagesActionJob failed", [
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
            \Log::warning('BulkMessagesActionJob: notify failed', ['error' => $e->getMessage()]);
        }
    }

    protected function setActive(MessageService $service, Message $message): void
    {
        $target = (bool) ($this->payload['is_active'] ?? true);
        if ((bool) $message->is_active === $target) return;
        // Set directo en el modelo: el update() del service espera el payload
        // completo del form (subject/body/audience_type). Aqui solo flipeamos
        // is_active, sin re-validar el resto.
        $message->update(['is_active' => $target]);
    }
}
