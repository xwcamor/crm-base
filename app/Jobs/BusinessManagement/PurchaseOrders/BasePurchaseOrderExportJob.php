<?php

namespace App\Jobs\BusinessManagement\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base abstracta para los 4 jobs de export (CSV / Excel / PDF / Word).
 *
 * Clon de BaseCustomerExportJob adaptado a PurchaseOrders (per-tenant, con
 * relaciones a supplier/warehouse/owner y filtros del modulo).
 */
abstract class BasePurchaseOrderExportJob implements ShouldQueue
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
        ini_set('memory_limit', config('purchase_orders.export_job_memory_limit', '512M'));

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

    /**
     * Apply scope: filtered / selected / all. Eager-load supplier/warehouse/owner
     * si las columnas estan pedidas.
     */
    protected function buildQuery()
    {
        $scope = $this->options['scope'] ?? 'filtered';
        $columns = $this->options['columns'] ?? ['supplier', 'warehouse', 'owner', 'creator'];

        $base = PurchaseOrder::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $base->where('purchase_orders.tenant_id', $this->tenantId);
        }

        if (in_array('supplier', $columns)) {
            $base->with('supplier:id,name');
        }
        if (in_array('warehouse', $columns)) {
            $base->with('warehouse:id,name,code');
        }
        if (in_array('owner', $columns)) {
            $base->with('owner:id,name,email');
        }

        if ($scope === 'selected' && !empty($this->options['selected_ids'])) {
            return $base->whereIn('purchase_orders.id', $this->options['selected_ids']);
        }
        if ($scope === 'all') {
            return $base;
        }

        $filters = $this->options['filters'] ?? [];
        $fakeReq = new \Illuminate\Http\Request($filters);
        // El modelo PurchaseOrder no expone scopeFilter aun — aplicamos los
        // filtros conocidos manualmente para que el job sea autosuficiente.
        return $this->applyFilters($base, $fakeReq);
    }

    /** Aplica filtros conocidos del modulo. Reflejo de Index del controller. */
    protected function applyFilters($q, \Illuminate\Http\Request $request)
    {
        $tbl = 'purchase_orders';

        if ($request->filled('reference')) {
            $q->where("{$tbl}.reference", 'like', '%' . $request->reference . '%');
        }
        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $statuses = array_filter($statuses, fn ($s) => $s !== '');
            if (!empty($statuses)) $q->whereIn("{$tbl}.status", $statuses);
        }
        if ($request->filled('supplier_company_id')) {
            $ids = is_array($request->supplier_company_id) ? $request->supplier_company_id : [$request->supplier_company_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.supplier_company_id", $ids);
        }
        if ($request->filled('warehouse_id')) {
            $ids = is_array($request->warehouse_id) ? $request->warehouse_id : [$request->warehouse_id];
            $ids = array_filter($ids);
            if (!empty($ids)) $q->whereIn("{$tbl}.warehouse_id", $ids);
        }
        if ($request->filled('order_date_from')) $q->where("{$tbl}.order_date", '>=', $request->order_date_from);
        if ($request->filled('order_date_to'))   $q->where("{$tbl}.order_date", '<=', $request->order_date_to);
        if ($request->filled('created_from'))    $q->where("{$tbl}.created_at", '>=', $request->created_from . ' 00:00:00');
        if ($request->filled('created_to'))      $q->where("{$tbl}.created_at", '<=', $request->created_to . ' 23:59:59');

        return $q->orderBy("{$tbl}.order_date", 'desc');
    }

    /**
     * Lista flat de filtros activos para la portada de PDF/Word.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function buildFiltersSummary(): array
    {
        $f = $this->options['filters'] ?? [];
        $out = [];

        if (!empty($f['reference'])) {
            $out[] = ['label' => __('purchase_orders.reference'), 'value' => (string) $f['reference']];
        }
        if (!empty($f['status'])) {
            $statuses = is_array($f['status']) ? $f['status'] : [$f['status']];
            $labels = array_map(fn ($s) => __('purchase_orders.status_options.' . $s), $statuses);
            $out[] = ['label' => __('purchase_orders.status'), 'value' => implode(', ', $labels)];
        }
        if (!empty($f['supplier_company_id'])) {
            $ids = is_array($f['supplier_company_id']) ? $f['supplier_company_id'] : [$f['supplier_company_id']];
            $ids = array_values(array_filter($ids));
            if (!empty($ids)) {
                $names = \App\Models\Company::whereIn('id', $ids)->pluck('name')->all();
                $out[] = ['label' => __('purchase_orders.supplier'), 'value' => implode(', ', $names) ?: implode(', ', $ids)];
            }
        }
        if (!empty($f['order_date_from']) || !empty($f['order_date_to'])) {
            $out[] = ['label' => __('purchase_orders.order_date'), 'value' => ($f['order_date_from'] ?? '…') . ' → ' . ($f['order_date_to'] ?? '…')];
        }
        if (!empty($f['created_from']) || !empty($f['created_to'])) {
            $out[] = ['label' => __('global.created_at'), 'value' => ($f['created_from'] ?? '…') . ' → ' . ($f['created_to'] ?? '…')];
        }

        return $out;
    }

    protected function generateFilename(): string
    {
        $base = Str::slug($this->options['title'] ?? __('purchase_orders.export_filename'));
        return $base . '_' . now()->format('Y-m-d_H-i-s') . '.' . $this->extension;
    }
}
