<?php

namespace App\Jobs\SystemManagement\Plans;

use App\Models\Plan;
use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base abstracta para los 4 jobs de export (CSV/Excel/PDF/Word).
 * Clon de BaseDiscountExportJob — Plans es super-only, sin tenant scoping.
 */
abstract class BasePlanExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    protected string $type;
    protected string $extension;

    protected int $userId;
    protected array $options;
    protected string $locale;
    protected string $userTimezone;
    protected ?Download $download = null;
    protected ?int $downloadId = null;

    public function __construct(int $userId, array $options = [])
    {
        $this->userId  = $userId;
        $this->options = $options;
        $this->locale  = app()->getLocale();
        $user = \App\Models\User::find($userId);
        $this->userTimezone = \App\Support\Tz::for($user);

        $this->download = Download::create([
            'slug'       => Str::random(22),
            'user_id'    => $userId,
            'type'       => $this->type,
            'filename'   => $this->generateFilename(),
            'path'       => '',
            'disk'       => 'local',
            'status'     => 'processing',
            'expires_at' => Download::computeExpiresAt(),
        ]);
        $this->downloadId = $this->download->id;
    }

    public function handle(): void
    {
        ini_set('memory_limit', config('plans.export_job_memory_limit', '512M'));

        app()->setLocale($this->locale);

        $this->download = Download::find($this->downloadId);
        if (!$this->download) return;

        if ($this->download->status !== 'processing') {
            $this->download->update(['status' => 'processing', 'error_message' => null]);
        }

        try {
            $this->executeExport($this->download);
        } catch (\Throwable $e) {
            $this->download->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            \Log::error(static::class . ' failed', [
                'download_id' => $this->downloadId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->downloadId) {
            Download::where('id', $this->downloadId)
                ->whereIn('status', ['processing', 'failed'])
                ->update([
                    'status'        => 'failed',
                    'error_message' => 'Job interrumpido: ' . substr($exception->getMessage(), 0, 200),
                ]);
        }

        \Log::error(static::class . ' permanently failed', [
            'download_id' => $this->downloadId,
            'user_id'     => $this->userId,
            'error'       => $exception->getMessage(),
        ]);
    }

    abstract protected function executeExport(Download $download): void;

    protected function buildQuery()
    {
        $scope   = $this->options['scope']   ?? 'filtered';
        $columns = $this->options['columns'] ?? ['creator'];

        $base = Plan::query();

        if (in_array('creator', $columns)) {
            $base->with('creator:id,name');
        }

        if ($scope === 'selected' && !empty($this->options['selected_ids'])) {
            return $base->whereIn('plans.id', $this->options['selected_ids']);
        }
        if ($scope === 'all') {
            return $base;
        }

        $filters = $this->options['filters'] ?? [];
        $fakeReq = new \Illuminate\Http\Request($filters);
        return $base->filter($fakeReq);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function buildFiltersSummary(): array
    {
        $f = $this->options['filters'] ?? [];
        $out = [];

        if (!empty($f['name'])) {
            $names = is_array($f['name']) ? $f['name'] : [$f['name']];
            $out[] = ['label' => __('plans.name'), 'value' => implode(', ', $names)];
        }
        if (!empty($f['slug'])) {
            $out[] = ['label' => __('plans.slug'), 'value' => (string) $f['slug']];
        }
        if (!empty($f['support_level'])) {
            $out[] = ['label' => __('plans.support_level'), 'value' => __('plans.support_' . $f['support_level'])];
        }
        if (isset($f['is_active']) && $f['is_active'] !== '' && $f['is_active'] !== null) {
            $bool = filter_var($f['is_active'], FILTER_VALIDATE_BOOLEAN);
            $out[] = ['label' => __('plans.is_active'), 'value' => $bool ? __('global.active') : __('global.inactive')];
        }
        if (isset($f['is_public']) && $f['is_public'] !== '' && $f['is_public'] !== null) {
            $bool = filter_var($f['is_public'], FILTER_VALIDATE_BOOLEAN);
            $out[] = ['label' => __('plans.is_public'), 'value' => $bool ? __('global.yes') : __('global.no')];
        }
        if (!empty($f['created_from']) || !empty($f['created_to'])) {
            $out[] = ['label' => __('global.created_at'), 'value' => ($f['created_from'] ?? '…') . ' → ' . ($f['created_to'] ?? '…')];
        }

        return $out;
    }

    protected function generateFilename(): string
    {
        $base = Str::slug($this->options['title'] ?? __('plans.export_filename'));
        return $base . '_' . now()->format('Y-m-d_H-i-s') . '.' . $this->extension;
    }
}
