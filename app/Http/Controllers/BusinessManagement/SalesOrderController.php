<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\SalesOrder\BulkDeleteSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\BulkRestoreSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\BulkSetActiveSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\DeleteSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\EditAllUpdateSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\ForceDeleteSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\ImportSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\StoreSalesOrderRequest;
use App\Http\Requests\BusinessManagement\SalesOrder\UpdateSalesOrderRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\SalesOrders\GenerateSalesOrdersCsvJob;
use App\Jobs\BusinessManagement\SalesOrders\GenerateSalesOrdersExcelJob;
use App\Jobs\BusinessManagement\SalesOrders\GenerateSalesOrdersPdfJob;
use App\Jobs\BusinessManagement\SalesOrders\GenerateSalesOrdersWordJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\BusinessManagement\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesOrderController extends Controller
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

        $with = ['creator:id,name,email', 'company:id,name,slug', 'warehouse:id,name,code', 'owner:id,name,email'];
        if ($isSuper) $with[] = 'tenant:id,name';

        $orders = SalesOrder::query()
            ->select('sales_orders.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = SalesOrder::count();

        $refs = $request->get('reference', '');
        if (is_array($refs)) {
            $refs = array_values(array_filter($refs, fn ($r) => $r !== ''));
        }

        return inertia('SalesOrders/Index', [
            'orders' => array_merge($orders->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('sales_orders'),
            'filters' => [
                'reference'      => $refs,
                'status'         => $request->get('status', []),
                'payment_status' => $request->get('payment_status', []),
                'company_id'     => $request->get('company_id', []),
                'warehouse_id'   => $request->get('warehouse_id', []),
                'order_from'     => $request->get('order_from', ''),
                'order_to'       => $request->get('order_to', ''),
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'companyOptions'        => $this->companyOptions(),
            'warehouseOptions'      => $this->warehouseOptionsLite(),
            'statusOptions'         => $this->statusOptions(),
            'paymentStatusOptions'  => $this->paymentStatusOptions(),
            'isSuper'               => $isSuper,
            'filterSchema'          => SalesOrder::filterSchema(),
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

    public function show(Request $request, SalesOrder $sales_order)
    {
        $sales_order->load([
            'items.product:id,name,sku',
            'company:id,name,slug,legal_name,tax_id',
            'contact:id,name,primary_email',
            'warehouse:id,name,code',
            'quote:id,reference,slug',
            'owner:id,name,email',
            'creator:id,name,email',
            'deleter:id,name,email',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', SalesOrder::class)
                    ->where('auditable_id', $sales_order->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        // Documentos del flujo: Invoice generado desde este SO + Deliveries.
        // Forward flow: SalesOrder -> Invoice / Deliveries.
        $relatedInvoice = \App\Models\Invoice::where('sales_order_id', $sales_order->id)
            ->orderByDesc('id')
            ->first(['id', 'slug', 'reference', 'status', 'grand_total']);
        $deliveries = \App\Models\Delivery::where('sales_order_id', $sales_order->id)
            ->orderByDesc('id')
            ->get(['id', 'slug', 'reference', 'status', 'delivered_at']);

        return inertia('SalesOrders/Show', [
            'order'           => $sales_order,
            'activity'        => $activity,
            'relatedInvoice'  => $relatedInvoice,
            'deliveries'      => $deliveries,
        ]);
    }

    /**
     * PDF individual de la orden de venta. Stream inline para abrir en el browser.
     */
    public function showPdf(SalesOrder $sales_order)
    {
        $sales_order->load([
            'items.product:id,name,sku,description',
            'company:id,name,slug,legal_name,tax_id,billing_email',
            'contact:id,name,primary_email,primary_phone,job_title',
            'warehouse:id,name,code',
            'owner:id,name,email',
        ]);

        $tenant = request()->user()?->tenant;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('business_management.sales_orders.pdf', [
            'order'  => $sales_order,
            'tenant' => $tenant,
        ])->setPaper('a4');

        $filename = ($sales_order->reference ?? 'sales-order-' . $sales_order->id) . '.pdf';
        return $pdf->stream($filename);
    }

    public function create()
    {
        return inertia('SalesOrders/Form', array_merge(
            ['order' => null],
            $this->formOptions()
        ));
    }

    public function store(StoreSalesOrderRequest $request, SalesOrderService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && SalesOrder::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $request->validated();
        // Si no vino reference, generar el next correlativo.
        if (empty($data['reference'])) {
            $data['reference'] = $this->nextReference($request->user()?->tenant_id);
        }

        $service->create($data);

        return redirect()
            ->route('business_management.sales_orders.index')
            ->with('success', __('sales_orders.created'));
    }

    public function edit(SalesOrder $sales_order)
    {
        $sales_order->load('items');
        $payload = $sales_order->only([
            'id', 'slug', 'reference', 'prefix', 'company_id', 'contact_id', 'owner_id',
            'status', 'warehouse_id', 'order_date', 'expected_delivery_date', 'currency_code',
            'subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total',
            'payment_terms_days', 'payment_status', 'notes', 'internal_notes',
        ]);
        $payload['items'] = $sales_order->items->map(fn ($i) => array_merge(
            $i->only(['product_id', 'name', 'sku', 'unit_price', 'discount_pct', 'tax_pct', 'sort_order']),
            ['quantity' => (float) $i->quantity_ordered]
        ));
        return inertia('SalesOrders/Form', array_merge(['order' => $payload], $this->formOptions()));
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $sales_order, SalesOrderService $service): RedirectResponse
    {
        $service->update($sales_order, $request->validated());

        return redirect()
            ->route('business_management.sales_orders.show', $sales_order->slug)
            ->with('success', __('sales_orders.saved'));
    }

    public function delete(SalesOrder $sales_order)
    {
        return inertia('SalesOrders/Delete', [
            'order' => $this->payload($sales_order),
        ]);
    }

    public function deleteSave(DeleteSalesOrderRequest $request, SalesOrder $sales_order, SalesOrderService $service): RedirectResponse
    {
        $service->delete($sales_order, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$sales_order->id]);

        return redirect()
            ->route('business_management.sales_orders.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$sales_order->id]));
    }

    /**
     * Legacy destroy — mantiene compat con rutas DELETE sin password confirmation.
     * Delega a service para que el audit log + soft-delete se generen igual.
     */
    public function destroy(SalesOrder $sales_order, SalesOrderService $service): RedirectResponse
    {
        $service->delete($sales_order, 'Eliminacion rapida');
        return redirect()
            ->route('business_management.sales_orders.index')
            ->with('success', __('sales_orders.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['sales_orders.recent_delete' => [
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

        $orders = SalesOrder::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('SalesOrders/Trash', [
            'orders'  => $orders,
            'filters' => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, SalesOrderService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = SalesOrder::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.sales_orders.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $orders = SalesOrder::query()
            ->filter($request)
            ->select('sales_orders.id', 'sales_orders.slug', 'sales_orders.reference', 'sales_orders.status', 'sales_orders.payment_status', 'sales_orders.grand_total', 'sales_orders.currency_code')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('SalesOrders/EditAll', [
            'orders'  => $orders,
            'filters' => [
                'reference' => $request->get('reference', ''),
                'status'    => $request->get('status', ''),
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
            'statusOptions'         => $this->statusOptions(),
            'paymentStatusOptions'  => $this->paymentStatusOptions(),
        ]);
    }

    public function editAllUpdate(EditAllUpdateSalesOrderRequest $request, SalesOrderService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.sales_orders.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, SalesOrder $sales_order, SalesOrderService $service): RedirectResponse
    {
        $clone = $service->duplicate($sales_order);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.sales_orders.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreSalesOrderRequest $request, SalesOrderService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.sales_orders.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.sales_orders.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteSalesOrderRequest $request, $slug, SalesOrderService $service): RedirectResponse
    {
        $model = SalesOrder::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['reference_confirmation']) !== $model->reference) {
            return back()->withErrors(['reference_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.sales_orders.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(SalesOrder $m, bool $withAudit = false): array
    {
        $m->loadMissing(['company:id,name,slug', 'warehouse:id,name,code']);
        $base = [
            'id'                     => $m->id,
            'slug'                   => $m->slug,
            'reference'              => $m->reference,
            'company_id'             => $m->company_id,
            'company'                => $m->company ? ['id' => $m->company->id, 'name' => $m->company->name, 'slug' => $m->company->slug] : null,
            'warehouse'              => $m->warehouse ? ['id' => $m->warehouse->id, 'name' => $m->warehouse->name, 'code' => $m->warehouse->code] : null,
            'status'                 => $m->status,
            'payment_status'         => $m->payment_status,
            'order_date'             => $m->order_date,
            'expected_delivery_date' => $m->expected_delivery_date,
            'currency_code'          => $m->currency_code,
            'grand_total'            => $m->grand_total,
            'is_favorite'            => (bool) ($m->is_favorite ?? false),
            'created_at'             => $m->created_at,
            'updated_at'             => $m->updated_at,
            'deleted_at'             => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $m->loadMissing(['creator:id,name,email', 'deleter:id,name,email']);
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateSalesOrdersCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateSalesOrdersExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateSalesOrdersPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateSalesOrdersWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('sales_orders', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('sales_orders.export_limit_exceeded', [
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
            return SalesOrder::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return SalesOrder::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS ──────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\SalesOrders\SalesOrdersImportTemplate(),
            __('sales_orders.import_template_filename')
        );
    }

    public function import(ImportSalesOrderRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\SalesOrders\SalesOrdersImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('SalesOrdersImport failed', [
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

    // ── BULK ─────────────────────────────────────────────────────────────

    public function bulkDelete(BulkDeleteSalesOrderRequest $request, SalesOrderService $service): RedirectResponse
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

    public function undoLastDelete(Request $request, SalesOrderService $service): RedirectResponse
    {
        $claim = session('sales_orders.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('sales_orders.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('sales_orders.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('sales_orders.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    /**
     * Bulk update de status. La ruta se llama `bulk_set_active` por consistencia
     * con el resto de los modulos (Customer/Product), pero aca el target NO
     * es un boolean sino un status enum.
     */
    public function bulkSetActive(BulkSetActiveSalesOrderRequest $request, SalesOrderService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (string) $data['status']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── Form options + helpers ──────────────────────────────────────────

    protected function nextReference(?int $tenantId): string
    {
        $year  = Carbon::now()->year;
        $count = SalesOrder::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('OV-%d-%04d', $year, $count);
    }

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'companyOptions'        => Company::orderBy('name')->limit(500)->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'contactOptions'        => Contact::orderBy('name')->limit(500)->get(['id', 'name', 'company_id'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'company_id' => $c->company_id])->all(),
            'productOptions'        => Product::where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id', 'name', 'sku', 'list_price'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name . ($p->sku ? ' [' . $p->sku . ']' : ''),
                    'name' => $p->name, 'sku' => $p->sku, 'price' => (float) $p->list_price])->all(),
            'warehouseOptions'      => Warehouse::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get(['id', 'name', 'code', 'is_default'])
                ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name . ' (' . $w->code . ')' . ($w->is_default ? ' *' : '')])->all(),
            'ownerOptions'          => (function () use ($u) {
                $q = \App\Models\User::query();
                if ($u && !$u->hasRole('super') && $u->tenant_id) $q->where('tenant_id', $u->tenant_id);
                return $q->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all();
            })(),
            'currencyOptions'       => \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['code', 'symbol'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' ' . $c->symbol])->all(),
            'statusOptions'         => $this->statusOptions(),
            'paymentStatusOptions'  => $this->paymentStatusOptions(),
            'defaultCurrencyCode'   => \App\Support\CurrencyResolver::forCurrentUser(),
            'defaultWarehouseId'    => Warehouse::where('tenant_id', $u?->tenant_id)->where('is_default', true)->value('id'),
            'nextReference'         => $this->nextReference($u?->tenant_id),
        ];
    }

    protected function companyOptions(): array
    {
        return Company::orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }

    protected function warehouseOptionsLite(): array
    {
        return Warehouse::where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'code'])
            ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name . ' (' . $w->code . ')'])->all();
    }

    protected function statusOptions(): array
    {
        return collect(SalesOrder::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('sales_orders.status_options.' . $s)])->all();
    }

    protected function paymentStatusOptions(): array
    {
        return collect(SalesOrder::PAYMENT_STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('sales_orders.payment_status_options.' . $s)])->all();
    }

    // ── Export helpers ──────────────────────────────────────────────────

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'reference', 'company', 'warehouse', 'status', 'payment_status',
            'order_date', 'expected_delivery_date', 'currency_code',
            'subtotal', 'tax_total', 'grand_total',
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
            'title'                   => $data['title']                   ?? __('sales_orders.export_title'),
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
            'auditable_type' => SalesOrder::class,
            'auditable_id'   => null,
            'module'         => 'sales_orders',
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
            'url'        => route('business_management.sales_orders.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
