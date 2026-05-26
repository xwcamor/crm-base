<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Delivery\BulkDeleteDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\BulkRestoreDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\BulkSetActiveDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\DeleteDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\EditAllUpdateDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\ForceDeleteDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\ImportDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\StoreDeliveryRequest;
use App\Http\Requests\BusinessManagement\Delivery\UpdateDeliveryRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\Deliveries\GenerateDeliveriesCsvJob;
use App\Jobs\BusinessManagement\Deliveries\GenerateDeliveriesExcelJob;
use App\Jobs\BusinessManagement\Deliveries\GenerateDeliveriesPdfJob;
use App\Jobs\BusinessManagement\Deliveries\GenerateDeliveriesWordJob;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\BusinessManagement\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = [
            'creator:id,name,email',
            'salesOrder:id,reference,slug,company_id',
            'salesOrder.company:id,name',
            'warehouse:id,name,code',
        ];
        if ($isSuper) $with[] = 'tenant:id,name';

        $deliveries = Delivery::query()
            ->select('deliveries.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Delivery::count();

        $refs = $request->get('reference', '');
        if (is_array($refs)) {
            $refs = array_values(array_filter($refs, fn ($r) => $r !== ''));
        }

        return inertia('Deliveries/Index', [
            'deliveries' => array_merge($deliveries->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('deliveries'),
            'filters' => [
                'reference'       => $refs,
                'status'          => $request->get('status', []),
                'warehouse_id'    => $request->get('warehouse_id', []),
                'sales_order_id'  => $request->get('sales_order_id', []),
                'carrier'         => $request->get('carrier', ''),
                'tracking_number' => $request->get('tracking_number', ''),
                'shipped_from'    => $request->get('shipped_from', ''),
                'shipped_to'      => $request->get('shipped_to', ''),
                'created_from'    => $request->get('created_from', ''),
                'created_to'      => $request->get('created_to', ''),
                'only_favorites'  => $request->boolean('only_favorites'),
                'sort'            => $request->get('sort', 'id'),
                'direction'       => $request->get('direction', 'desc'),
                'per_page'        => $perPage,
                'advanced_where'  => $this->parseAdvancedWhere($request),
            ],
            'warehouseOptions'  => $this->warehouseOptionsLite(),
            'statusOptions'     => $this->statusOptions(),
            'salesOrderOptions' => $this->salesOrderOptionsLite(),
            'isSuper'           => $isSuper,
            'filterSchema'      => Delivery::filterSchema(),
        ]);
    }

    protected function parseAdvancedWhere(Request $request): array
    {
        $raw = $request->input('advanced_where', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) return [];

        return array_values(array_filter($raw, fn ($c) =>
            is_array($c) && !empty($c['field']) && !empty($c['op'])
        ));
    }

    public function show(Request $request, Delivery $delivery)
    {
        $delivery->load([
            'items.product:id,name,sku',
            'salesOrder:id,reference,slug,company_id,warehouse_id',
            'salesOrder.company:id,name,slug',
            'warehouse:id,name,code',
            'creator:id,name,email',
            'deleter:id,name,email',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Delivery::class)
                    ->where('auditable_id', $delivery->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('Deliveries/Show', [
            'delivery' => $delivery,
            'activity' => $activity,
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Deliveries/Form', array_merge(
            [
                'delivery'                => null,
                'preselectedSalesOrderId' => (int) $request->get('sales_order_id', 0) ?: null,
            ],
            $this->formOptions()
        ));
    }

    public function store(StoreDeliveryRequest $request, DeliveryService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Delivery::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $request->validated();
        if (empty($data['reference'])) {
            $data['reference'] = $this->nextReference($request->user()?->tenant_id);
        }

        $delivery = $service->create($data);

        return redirect()
            ->route('business_management.deliveries.show', $delivery->slug)
            ->with('success', __('deliveries.created'));
    }

    public function edit(Delivery $delivery)
    {
        $delivery->load('items', 'salesOrder.items.product:id,name,sku');
        return inertia('Deliveries/Form', array_merge(
            [
                'delivery'                => $delivery,
                'preselectedSalesOrderId' => $delivery->sales_order_id,
            ],
            $this->formOptions()
        ));
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery, DeliveryService $service): RedirectResponse
    {
        $service->update($delivery, $request->validated());

        return redirect()
            ->route('business_management.deliveries.show', $delivery->slug)
            ->with('success', __('deliveries.saved'));
    }

    public function delete(Delivery $delivery)
    {
        return inertia('Deliveries/Delete', [
            'delivery' => $this->payload($delivery),
        ]);
    }

    public function deleteSave(DeleteDeliveryRequest $request, Delivery $delivery, DeliveryService $service): RedirectResponse
    {
        $service->delete($delivery, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$delivery->id]);

        return redirect()
            ->route('business_management.deliveries.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$delivery->id]));
    }

    /**
     * Legacy destroy — mantiene compat con rutas DELETE sin password confirmation.
     */
    public function destroy(Delivery $delivery, DeliveryService $service): RedirectResponse
    {
        $service->delete($delivery, 'Eliminacion rapida');
        return redirect()
            ->route('business_management.deliveries.index')
            ->with('success', __('deliveries.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['deliveries.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    protected function buildRecentDeletePayload(array $ids): array
    {
        return ['count' => count($ids), 'seconds' => 60];
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $reference = $request->get('reference', '');
        $perPage   = (int) $request->get('per_page', 25);
        $perPage   = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $deliveries = Delivery::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Deliveries/Trash', [
            'deliveries' => $deliveries,
            'filters' => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, DeliveryService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Delivery::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.deliveries.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $deliveries = Delivery::query()
            ->filter($request)
            ->select('deliveries.id', 'deliveries.slug', 'deliveries.reference', 'deliveries.status', 'deliveries.carrier')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Deliveries/EditAll', [
            'deliveries' => $deliveries,
            'filters' => [
                'reference' => $request->get('reference', ''),
                'status'    => $request->get('status', ''),
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function editAllUpdate(EditAllUpdateDeliveryRequest $request, DeliveryService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.deliveries.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, Delivery $delivery, DeliveryService $service): RedirectResponse
    {
        $clone = $service->duplicate($delivery);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.deliveries.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreDeliveryRequest $request, DeliveryService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.deliveries.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.deliveries.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteDeliveryRequest $request, $slug, DeliveryService $service): RedirectResponse
    {
        $model = Delivery::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['reference_confirmation']) !== $model->reference) {
            return back()->withErrors(['reference_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.deliveries.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Delivery $m, bool $withAudit = false): array
    {
        $m->loadMissing(['salesOrder:id,reference,slug', 'warehouse:id,name,code']);
        $base = [
            'id'              => $m->id,
            'slug'            => $m->slug,
            'reference'       => $m->reference,
            'sales_order'     => $m->salesOrder ? ['id' => $m->salesOrder->id, 'reference' => $m->salesOrder->reference, 'slug' => $m->salesOrder->slug] : null,
            'warehouse'       => $m->warehouse ? ['id' => $m->warehouse->id, 'name' => $m->warehouse->name, 'code' => $m->warehouse->code] : null,
            'status'          => $m->status,
            'carrier'         => $m->carrier,
            'tracking_number' => $m->tracking_number,
            'shipped_at'      => $m->shipped_at,
            'delivered_at'    => $m->delivered_at,
            'shipping_cost'   => $m->shipping_cost,
            'is_favorite'     => (bool) ($m->is_favorite ?? false),
            'created_at'      => $m->created_at,
            'updated_at'      => $m->updated_at,
            'deleted_at'      => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $m->loadMissing(['creator:id,name,email', 'deleter:id,name,email']);
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    /** Endpoint AJAX: devuelve lineas pendientes de fulfillment de una OV */
    public function getSalesOrderLines(SalesOrder $sales_order)
    {
        $sales_order->load('items.product:id,name,sku');
        return response()->json($sales_order->items->map(fn ($i) => [
            'sales_order_item_id' => $i->id,
            'product_id'          => $i->product_id,
            'name'                => $i->name,
            'sku'                 => $i->sku,
            'quantity_ordered'    => (float) $i->quantity_ordered,
            'quantity_fulfilled'  => (float) $i->quantity_fulfilled,
            'quantity_pending'    => max(0, (float) $i->quantity_ordered - (float) $i->quantity_fulfilled),
        ]));
    }

    // -- EXPORTS ---------------------------------------------------------

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateDeliveriesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateDeliveriesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateDeliveriesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateDeliveriesWordJob::class);
    }

    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('deliveries', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('deliveries.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return Delivery::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Delivery::query()->filter($fakeReq)->count();
    }

    // -- IMPORTS ---------------------------------------------------------

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Deliveries\DeliveriesImportTemplate(),
            __('deliveries.import_template_filename')
        );
    }

    public function import(ImportDeliveryRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\Deliveries\DeliveriesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('DeliveriesImport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => $this->humanizeImportError($e),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => $importer->summary(),
        ], 200);
    }

    protected function humanizeImportError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if ($e instanceof \Illuminate\Database\QueryException) {
            if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate'))      return __('imports.err_unique_violation');
            if (str_contains($msg, 'NOT NULL') || str_contains($msg, 'null value'))    return __('imports.err_not_null_violation');
            if (str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign')) return __('imports.err_foreign_key_violation');
        }

        return __('imports.process_failed');
    }

    // -- BULK ------------------------------------------------------------

    public function bulkDelete(BulkDeleteDeliveryRequest $request, DeliveryService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    public function undoLastDelete(Request $request, DeliveryService $service): RedirectResponse
    {
        $claim = session('deliveries.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('deliveries.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('deliveries.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('deliveries.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    /**
     * Bulk update de status. La ruta se llama `bulk_set_active` por consistencia
     * con el resto de los modulos, pero aqui el target NO es un boolean sino
     * un status enum.
     */
    public function bulkSetActive(BulkSetActiveDeliveryRequest $request, DeliveryService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (string) $data['status']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // -- Form options + helpers ------------------------------------------

    protected function nextReference(?int $tenantId): string
    {
        $year  = Carbon::now()->year;
        $count = Delivery::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('DEL-%d-%04d', $year, $count);
    }

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'salesOrderOptions' => SalesOrder::whereIn('status', ['pending', 'processing', 'partially_shipped', 'shipped'])
                ->with('company:id,name')->orderBy('order_date', 'desc')->limit(500)
                ->get(['id', 'reference', 'company_id', 'warehouse_id'])
                ->map(fn ($o) => [
                    'value'        => $o->id,
                    'label'        => $o->reference . ' - ' . ($o->company?->name ?? '?'),
                    'warehouse_id' => $o->warehouse_id,
                ])->all(),
            'warehouseOptions' => Warehouse::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')
                ->get(['id', 'name', 'code', 'is_default'])
                ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name . ' (' . $w->code . ')' . ($w->is_default ? ' *' : '')])->all(),
            'statusOptions'      => $this->statusOptions(),
            'defaultWarehouseId' => Warehouse::where('tenant_id', $u?->tenant_id)->where('is_default', true)->value('id'),
            'nextReference'      => $this->nextReference($u?->tenant_id),
        ];
    }

    protected function warehouseOptionsLite(): array
    {
        return Warehouse::where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'code'])
            ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name . ' (' . $w->code . ')'])->all();
    }

    protected function salesOrderOptionsLite(): array
    {
        return SalesOrder::orderBy('order_date', 'desc')->limit(200)->get(['id', 'reference'])
            ->map(fn ($o) => ['value' => $o->id, 'label' => $o->reference])->all();
    }

    protected function statusOptions(): array
    {
        return collect(Delivery::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('deliveries.status_options.' . $s)])->all();
    }

    // -- Export helpers --------------------------------------------------

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'reference', 'sales_order', 'warehouse', 'status',
            'carrier', 'tracking_number', 'shipping_method', 'shipping_cost',
            'shipped_at', 'delivered_at', 'signed_by_name', 'notes',
            'slug', 'created_at', 'updated_at', 'creator'];

        $rules = [
            'scope'                   => 'nullable|in:filtered,selected,all',
            'selected_ids'            => 'array',
            'selected_ids.*'          => 'integer',
            'columns'                 => 'array|min:1',
            'columns.*'               => 'in:' . implode(',', $allowedColumns),
            'title'                   => 'nullable|string|max:120',
            'include_filters_summary' => 'boolean',
            'filters'                 => 'array',
        ];
        if ($format === 'pdf') {
            $rules['orientation'] = 'nullable|in:portrait,landscape';
            $rules['paper_size']  = 'nullable|in:a4,letter';
        }
        if ($format === 'excel') {
            $rules['autofilter']    = 'boolean';
            $rules['freeze_header'] = 'boolean';
        }

        $data = $request->validate($rules);

        return [
            'scope'                   => $data['scope']                   ?? 'filtered',
            'selected_ids'            => $data['selected_ids']            ?? [],
            'columns'                 => $data['columns']                 ?? $allowedColumns,
            'title'                   => $data['title']                   ?? __('deliveries.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'landscape',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => Delivery::class,
            'auditable_id'   => null,
            'module'         => 'deliveries',
            'old_values'     => null,
            'new_values'     => [
                'format'                  => $format,
                'scope'                   => $options['scope']        ?? 'filtered',
                'columns'                 => $options['columns']      ?? [],
                'title'                   => $options['title']        ?? null,
                'orientation'             => $format === 'pdf'   ? ($options['orientation']    ?? null) : null,
                'paper_size'              => $format === 'pdf'   ? ($options['paper_size']     ?? null) : null,
                'autofilter'              => $format === 'excel' ? ($options['autofilter']     ?? null) : null,
                'freeze_header'           => $format === 'excel' ? ($options['freeze_header']  ?? null) : null,
                'include_filters_summary' => $options['include_filters_summary'] ?? false,
                'filters'                 => $options['filters']      ?? [],
                'selected_ids_count'      => count($options['selected_ids'] ?? []),
            ],
            'url'        => route('business_management.deliveries.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
