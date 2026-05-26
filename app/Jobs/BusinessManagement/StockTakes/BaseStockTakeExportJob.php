<?php

namespace App\Jobs\BusinessManagement\StockTakes;

use App\Models\Download;
use App\Models\StockTake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base abstracta para los 4 jobs de export (CSV / Excel / PDF / Word).
 * Clon de BaseSalesOrderExportJob adaptado a StockTakes.
 */
abstract class BaseStockTakeExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    protected string $type;
    protected string $extension;

    protected int $userId;
    protected array $options;
    protected string $locale;
    protected string $userTimezone;
    protected ?Download $download = null;
    protected ?int $tenantId = null;
    protected ?int $downloadId = null;

    public function __construct(int $userId, array $options = [])
    {
        $this->userId   = $userId;
        $this->options  = $options;
        $this->locale   = app()->getLocale();
        $user = \App\Models\User::find($userId);
        $this->tenantId     = $user?->tenant_id;
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
        ini_set('memory_limit', config('stock_takes.export_job_memory_limit', '512M'));
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
        $columns = $this->options['columns'] ?? ['creator', 'warehouse'];

        $base = StockTake::query()->withoutGlobalScopes();
        if ($this->tenantId !== null) {
            $base->where('stock_takes.tenant_id', $this->tenantId);
        }

        if (in_array('creator', $columns))   $base->with('creator:id,name');
        if (in_array('warehouse', $columns)) $base->with('warehouse:id,name,code');

        if ($scope === 'selected' && !empty($this->options['selected_ids'])) {
            return $base->whereIn('stock_takes.id', $this->options['selected_ids']);
        }
        if ($scope === 'all') {
            return $base;
        }

        // Filtros server-side basicos (status, reference, warehouse) sin scopeFilter
        // — StockTake usa el scopeFilter completo, pero aqui filtramos lo basico
        // para no traer dependencia del Request object.
        $filters = $this->options['filters'] ?? [];
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $statuses = array_filter($statuses);
            if (!empty($statuses)) $base->whereIn('stock_takes.status', $statuses);
        }
        if (!empty($filters['reference'])) {
            $refs = is_array($filters['reference']) ? $filters['reference'] : [$filters['reference']];
            $refs = array_filter($refs);
            if (!empty($refs)) {
                $base->where(function ($q) use ($refs) {
                    foreach ($refs as $r) {
                        $q->orWhere('stock_takes.reference', 'like', '%' . $r . '%');
                    }
                });
            }
        }
        if (!empty($filters['warehouse_id'])) {
            $ids = is_array($filters['warehouse_id']) ? $filters['warehouse_id'] : [$filters['warehouse_id']];
            $base->whereIn('stock_takes.warehouse_id', array_filter($ids));
        }
        if (!empty($filters['created_from'])) {
            $base->where('stock_takes.created_at', '>=', $filters['created_from'] . ' 00:00:00');
        }
        if (!empty($filters['created_to'])) {
            $base->where('stock_takes.created_at', '<=', $filters['created_to'] . ' 23:59:59');
        }
        return $base;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    protected function buildFiltersSummary(): array
    {
        $f = $this->options['filters'] ?? [];
        $out = [];

        if (!empty($f['reference'])) {
            $val = is_array($f['reference']) ? implode(', ', $f['reference']) : (string) $f['reference'];
            $out[] = ['label' => __('stock_takes.reference'), 'value' => $val];
        }
        if (!empty($f['status'])) {
            $statuses = is_array($f['status']) ? $f['status'] : [$f['status']];
            $labels = array_map(fn ($s) => __('stock_takes.status_options.' . $s), $statuses);
            $out[] = ['label' => __('stock_takes.status'), 'value' => implode(', ', $labels)];
        }
        if (!empty($f['warehouse_id'])) {
            $ids = is_array($f['warehouse_id']) ? $f['warehouse_id'] : [$f['warehouse_id']];
            $ids = array_values(array_filter($ids));
            if (!empty($ids)) {
                $names = \App\Models\Warehouse::whereIn('id', $ids)->pluck('name')->all();
                $out[] = ['label' => __('stock_takes.warehouse'), 'value' => implode(', ', $names) ?: implode(', ', $ids)];
            }
        }
        if (!empty($f['created_from']) || !empty($f['created_to'])) {
            $out[] = ['label' => __('global.created_at'), 'value' => ($f['created_from'] ?? '…') . ' → ' . ($f['created_to'] ?? '…')];
        }
        return $out;
    }

    protected function generateFilename(): string
    {
        $base = Str::slug($this->options['title'] ?? __('stock_takes.export_filename'));
        return $base . '_' . now()->format('Y-m-d_H-i-s') . '.' . $this->extension;
    }
}
