<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\PurchaseOrder\BulkDeletePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\BulkRestorePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\BulkSetActivePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\DeletePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\EditAllUpdatePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\ForceDeletePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\ImportPurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\BusinessManagement\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\PurchaseOrders\GeneratePurchaseOrdersCsvJob;
use App\Jobs\BusinessManagement\PurchaseOrders\GeneratePurchaseOrdersExcelJob;
use App\Jobs\BusinessManagement\PurchaseOrders\GeneratePurchaseOrdersPdfJob;
use App\Jobs\BusinessManagement\PurchaseOrders\GeneratePurchaseOrdersWordJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BusinessManagement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'order_date', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = [
            'supplier:id,name',
            'warehouse:id,name,code',
            'owner:id,name,email',
            'creator:id,name,email',
        ];
        if ($isSuper) $with[] = 'tenant:id,name';

        $orders = PurchaseOrder::query()
            ->select('purchase_orders.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = PurchaseOrder::count();

        return inertia('PurchaseOrders/Index', [
            'orders' => array_merge($orders->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('purchase_orders'),
            'filters' => [
                'reference'           => $request->get('reference', ''),
                'status'              => $request->get('status', []),
                'supplier_company_id' => $request->get('supplier_company_id', []),
                'warehouse_id'        => $request->get('warehouse_id', []),
                'order_date_from'     => $request->get('order_date_from', ''),
                'order_date_to'       => $request->get('order_date_to', ''),
                'created_from'        => $request->get('created_from', ''),
                'created_to'          => $request->get('created_to', ''),
                'only_favorites'      => $request->boolean('only_favorites'),
                'sort'                => $request->get('sort', 'order_date'),
                'direction'           => $request->get('direction', 'desc'),
                'per_page'            => $perPage,
                'advanced_where'      => $this->parseAdvancedWhere($request),
            ],
            'isSuper'        => $isSuper,
            'statusOptions'  => $this->statusOptions(),
            'supplierOptions' => $this->supplierOptions(),
            'warehouseOptions' => $this->warehouseOptions(),
            'filterSchema'   => PurchaseOrder::filterSchema(),
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

    public function show(Request $request, PurchaseOrder $purchase_order)
    {
        $purchase_order->load([
            'items.product:id,name,sku',
            'supplier:id,name,legal_name,tax_id,slug',
            'warehouse:id,name,code',
            'owner:id,name,email',
            'creator:id,name,email',
            'deleter:id,name,email',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', PurchaseOrder::class)
                    ->where('auditable_id', $purchase_order->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('PurchaseOrders/Show', [
            'order'    => $purchase_order,
            'activity' => $activity,
        ]);
    }

    public function create(Request $request)
    {
        return inertia('PurchaseOrders/Form', array_merge(['order' => null], $this->formOptions()));
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        $purchase_order->load('items');
        $payload = $purchase_order->only([
            'id', 'slug', 'reference', 'prefix', 'supplier_company_id', 'owner_id', 'warehouse_id', 'status',
            'order_date', 'expected_delivery_date', 'currency_code',
            'subtotal', 'tax_total', 'discount_total', 'shipping_cost', 'grand_total',
            'payment_terms_days', 'delivery_type', 'terms_md', 'notes',
        ]);
        $payload['items'] = $purchase_order->items->map(fn ($i) => array_merge($i->only([
            'product_id', 'name', 'description', 'quantity_ordered', 'unit_cost',
            'discount_pct', 'tax_pct', 'sort_order',
        ]), [
            // El form usa 'quantity' / 'unit_price' como nombres canonicos
            // (mismo shape que Quotes/SalesOrders) — mapeamos desde los nombres
            // PO-especificos en BD.
            'quantity'   => $i->quantity_ordered,
            'unit_price' => $i->unit_cost,
        ]));
        return inertia('PurchaseOrders/Form', array_merge(['order' => $payload], $this->formOptions()));
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && PurchaseOrder::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data    = $request->validated();
        $header  = $this->headerFromValidated($data, $request);
        if (empty($header['reference'])) {
            $header['reference'] = $service->nextReference($header['tenant_id'] ?? null);
        }

        $service->create(['header' => $header, 'items' => $data['items'] ?? []]);

        return redirect()
            ->route('business_management.purchase_orders.index')
            ->with('success', __('purchase_orders.created'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order, PurchaseOrderService $service): RedirectResponse
    {
        $data   = $request->validated();
        $header = collect($data)->only([
            'reference', 'supplier_company_id', 'owner_id', 'warehouse_id', 'status',
            'order_date', 'expected_delivery_date', 'currency_code',
            'payment_terms_days', 'delivery_type', 'notes',
        ])->toArray();

        $service->update($purchase_order, ['header' => $header, 'items' => $data['items'] ?? []]);

        return redirect()
            ->route('business_management.purchase_orders.show', $purchase_order->slug)
            ->with('success', __('purchase_orders.saved'));
    }

    public function delete(PurchaseOrder $purchase_order)
    {
        return inertia('PurchaseOrders/Delete', [
            'order' => $this->payload($purchase_order),
        ]);
    }

    public function deleteSave(DeletePurchaseOrderRequest $request, PurchaseOrder $purchase_order, PurchaseOrderService $service): RedirectResponse
    {
        $service->delete($purchase_order, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$purchase_order->id]);

        return redirect()
            ->route('business_management.purchase_orders.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$purchase_order->id]));
    }

    public function destroy(PurchaseOrder $purchase_order, PurchaseOrderService $service): RedirectResponse
    {
        $service->delete($purchase_order, 'destroy via API');
        return redirect()->route('business_management.purchase_orders.index')->with('success', __('purchase_orders.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['purchase_orders.recent_delete' => [
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

        $orders = PurchaseOrder::onlyTrashed()
            ->with(['supplier:id,name', 'deleter:id,name,email'])
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('PurchaseOrders/Trash', [
            'orders'  => $orders,
            'filters' => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, PurchaseOrderService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = PurchaseOrder::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.purchase_orders.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $orders = PurchaseOrder::query()
            ->filter($request)
            ->select('purchase_orders.id', 'purchase_orders.slug', 'purchase_orders.reference', 'purchase_orders.status')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('PurchaseOrders/EditAll', [
            'orders'  => $orders,
            'filters' => [
                'reference' => $request->get('reference', ''),
                'status'    => $request->get('status', []),
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function editAllUpdate(EditAllUpdatePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.purchase_orders.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, PurchaseOrder $purchase_order, PurchaseOrderService $service): RedirectResponse
    {
        $clone = $service->duplicate($purchase_order);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.purchase_orders.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestorePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.purchase_orders.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.purchase_orders.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeletePurchaseOrderRequest $request, $slug, PurchaseOrderService $service): RedirectResponse
    {
        $model = PurchaseOrder::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['reference_confirmation']) !== $model->reference) {
            return back()->withErrors(['reference_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.purchase_orders.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    public function bulkDelete(BulkDeletePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
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

    public function undoLastDelete(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        $claim = session('purchase_orders.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('purchase_orders.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('purchase_orders.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('purchase_orders.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActivePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetStatus($data['ids'], $data['status']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)   { return $this->dispatchExport($request, 'csv',   GeneratePurchaseOrdersCsvJob::class); }
    public function exportExcel(Request $request) { return $this->dispatchExport($request, 'excel', GeneratePurchaseOrdersExcelJob::class); }
    public function exportPdf(Request $request)   { return $this->dispatchExport($request, 'pdf',   GeneratePurchaseOrdersPdfJob::class); }
    public function exportWord(Request $request)  { return $this->dispatchExport($request, 'word',  GeneratePurchaseOrdersWordJob::class); }

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

        $limit = \App\Models\Setting::getExportLimit('purchase_orders', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('purchase_orders.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') return count($options['selected_ids'] ?? []);
        if ($scope === 'all')      return PurchaseOrder::query()->count();

        $fakeReq = new Request($options['filters'] ?? []);
        return PurchaseOrder::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS ─────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\PurchaseOrders\PurchaseOrdersImportTemplate(),
            __('purchase_orders.import_template_filename')
        );
    }

    public function import(ImportPurchaseOrderRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\PurchaseOrders\PurchaseOrdersImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('PurchaseOrdersImport failed', [
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
            if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate')) {
                return __('imports.err_unique_violation');
            }
            if (str_contains($msg, 'NOT NULL') || str_contains($msg, 'null value')) {
                return __('imports.err_not_null_violation');
            }
            if (str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign')) {
                return __('imports.err_foreign_key_violation');
            }
        }

        return __('imports.process_failed');
    }

    // ── Export helpers ──────────────────────────────────────────────────

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = [
            'id', 'reference', 'supplier', 'warehouse', 'status',
            'order_date', 'expected_delivery_date', 'currency_code',
            'subtotal', 'tax_total', 'grand_total', 'owner', 'slug',
            'created_at', 'updated_at', 'creator',
        ];

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
            'title'                   => $data['title']                   ?? __('purchase_orders.export_title'),
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
            'auditable_type' => PurchaseOrder::class,
            'auditable_id'   => null,
            'module'         => 'purchase_orders',
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
            'url'        => route('business_management.purchase_orders.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function headerFromValidated(array $data, Request $request): array
    {
        $header = collect($data)->only([
            'reference', 'supplier_company_id', 'owner_id', 'warehouse_id', 'status',
            'order_date', 'expected_delivery_date', 'currency_code',
            'payment_terms_days', 'delivery_type', 'notes',
        ])->toArray();
        $header['tenant_id'] = $request->user()?->tenant_id;
        $header['prefix']    = 'PO';
        return $header;
    }

    protected function payload(PurchaseOrder $m): array
    {
        $m->loadMissing([
            'supplier:id,name,slug,legal_name,tax_id',
            'warehouse:id,name,code',
            'owner:id,name,email',
        ]);
        return [
            'id'                     => $m->id,
            'slug'                   => $m->slug,
            'reference'              => $m->reference,
            'supplier'               => $m->supplier ? ['id' => $m->supplier->id, 'name' => $m->supplier->name] : null,
            'warehouse'              => $m->warehouse ? ['id' => $m->warehouse->id, 'name' => $m->warehouse->name] : null,
            'owner'                  => $m->owner ? ['id' => $m->owner->id, 'name' => $m->owner->name] : null,
            'status'                 => $m->status,
            'order_date'             => $m->order_date?->format('Y-m-d'),
            'expected_delivery_date' => $m->expected_delivery_date?->format('Y-m-d'),
            'currency_code'          => $m->currency_code,
            'grand_total'            => $m->grand_total,
            'is_favorite'            => (bool) ($m->is_favorite ?? false),
            'created_at'             => $m->created_at,
            'updated_at'             => $m->updated_at,
            'deleted_at'             => $m->deleted_at,
        ];
    }

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'supplierOptions'   => $this->supplierOptions(),
            'productOptions'    => Product::where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id', 'name', 'sku', 'cost', 'list_price'])
                ->map(fn ($p) => [
                    'value' => $p->id, 'label' => $p->name . ($p->sku ? ' [' . $p->sku . ']' : ''),
                    'name'  => $p->name, 'sku' => $p->sku,
                    'price' => (float) ($p->cost ?? $p->list_price * 0.4),
                ])->all(),
            'warehouseOptions'  => $this->warehouseOptions(),
            'ownerOptions'      => (function () use ($u) {
                $q = User::query();
                if ($u && !$u->hasRole('super') && $u->tenant_id) $q->where('tenant_id', $u->tenant_id);
                return $q->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all();
            })(),
            'currencyOptions'   => Currency::where('is_active', true)->orderBy('code')->get(['code', 'symbol'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' ' . $c->symbol])->all(),
            'statusOptions'     => $this->statusOptions(),
            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
            'defaultWarehouseId' => Warehouse::where('tenant_id', $u?->tenant_id)->where('is_default', true)->value('id'),
            'nextReference'     => app(PurchaseOrderService::class)->nextReference($u?->tenant_id),
        ];
    }

    protected function supplierOptions(): array
    {
        return Company::whereIn('company_type', ['supplier', 'both', 'partner'])
            ->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }

    protected function warehouseOptions(): array
    {
        return Warehouse::where('is_active', true)
            ->orderBy('is_default', 'desc')->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default'])
            ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name . ' (' . $w->code . ')' . ($w->is_default ? ' ★' : '')])->all();
    }

    protected function statusOptions(): array
    {
        return collect(PurchaseOrder::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('purchase_orders.status_options.' . $s)])->all();
    }
}
