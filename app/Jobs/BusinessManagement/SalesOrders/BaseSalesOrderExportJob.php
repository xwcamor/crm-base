<?php

namespace App\Jobs\BusinessManagement\SalesOrders;

use App\Models\Download;
use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base abstracta para los 4 jobs de export (CSV / Excel / PDF / Word).
 * Clon de BaseCustomerExportJob adaptado a SalesOrders.
 */
abstract class BaseSalesOrderExportJob implements ShouldQueue
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
        ini_set('memory_limit', config('sales_orders.export_job_memory_limit', '512M'));
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
        $columns = $this->options['columns'] ?? ['creator', 'company', 'warehouse'];

        $base = SalesOrder::query()->withoutGlobalScopes();
        if ($this->tenantId !== null) {
            $base->where('sales_orders.tenant_id', $this->tenantId);
        }

        if (in_array('creator', $columns))   $base->with('creator:id,name');
        if (in_array('company', $columns))   $base->with('company:id,name');
        if (in_array('warehouse', $columns)) $base->with('warehouse:id,name,code');
        if (in_array('owner', $columns))     $base->with('owner:id,name,email');

        if ($scope === 'selected' && !empty($this->options['selected_ids'])) {
            return $base->whereIn('sales_orders.id', $this->options['selected_ids']);
        }
        if ($scope === 'all') {
            return $base;
        }

        // Aplicar filtros server-side basicos (status, reference) sin scopeFilter
        // — SalesOrder no tiene scopeFilter rico todavia (Tier 1 minimo).
        $filters = $this->options['filters'] ?? [];
        if (!empty($filters['status'])) {
            $base->where('sales_orders.status', $filters['status']);
        }
        if (!empty($filters['payment_status'])) {
            $base->where('sales_orders.payment_status', $filters['payment_status']);
        }
        if (!empty($filters['reference'])) {
            $base->where('sales_orders.reference', 'like', '%' . $filters['reference'] . '%');
        }
        if (!empty($filters['company_id'])) {
            $cid = is_array($filters['company_id']) ? $filters['company_id'] : [$filters['company_id']];
            $base->whereIn('sales_orders.company_id', array_filter($cid));
        }
        if (!empty($filters['created_from'])) {
            $base->where('sales_orders.created_at', '>=', $filters['created_from'] . ' 00:00:00');
        }
        if (!empty($filters['created_to'])) {
            $base->where('sales_orders.created_at', '<=', $filters['created_to'] . ' 23:59:59');
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
            $out[] = ['label' => __('sales_orders.reference'), 'value' => (string) $f['reference']];
        }
        if (!empty($f['status'])) {
            $out[] = ['label' => __('sales_orders.status'), 'value' => __('sales_orders.status_options.' . $f['status'])];
        }
        if (!empty($f['payment_status'])) {
            $out[] = ['label' => __('sales_orders.payment_status'), 'value' => __('sales_orders.payment_status_options.' . $f['payment_status'])];
        }
        if (!empty($f['company_id'])) {
            $ids = is_array($f['company_id']) ? $f['company_id'] : [$f['company_id']];
            $ids = array_values(array_filter($ids));
            if (!empty($ids)) {
                $names = \App\Models\Company::whereIn('id', $ids)->pluck('name')->all();
                $out[] = ['label' => __('sales_orders.company'), 'value' => implode(', ', $names) ?: implode(', ', $ids)];
            }
        }
        if (!empty($f['created_from']) || !empty($f['created_to'])) {
            $out[] = ['label' => __('global.created_at'), 'value' => ($f['created_from'] ?? '…') . ' → ' . ($f['created_to'] ?? '…')];
        }
        return $out;
    }

    protected function generateFilename(): string
    {
        $base = Str::slug($this->options['title'] ?? __('sales_orders.export_filename'));
        return $base . '_' . now()->format('Y-m-d_H-i-s') . '.' . $this->extension;
    }
}
