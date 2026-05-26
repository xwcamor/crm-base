<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Invoice\BulkDeleteInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\BulkRestoreInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\BulkSetActiveInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\DeleteInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\EditAllUpdateInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\ForceDeleteInvoiceRequest;
use App\Http\Requests\BusinessManagement\Invoice\ImportInvoiceRequest;
use App\Jobs\BusinessManagement\Invoices\GenerateInvoicesCsvJob;
use App\Jobs\BusinessManagement\Invoices\GenerateInvoicesExcelJob;
use App\Jobs\BusinessManagement\Invoices\GenerateInvoicesPdfJob;
use App\Jobs\BusinessManagement\Invoices\GenerateInvoicesWordJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\BusinessManagement\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = ['creator:id,name,email', 'company:id,name,slug', 'contact:id,name,slug', 'owner:id,name,email'];
        if ($isSuper) $with[] = 'tenant:id,name';

        $query = Invoice::query()
            ->select('invoices.*')
            ->with($with)
            ->filter($request);

        $invoices = $query->paginate($perPage)->withQueryString();
        $totalUnfiltered = Invoice::count();

        $nums = $request->get('number', '');
        if (is_array($nums)) {
            $nums = array_values(array_filter($nums, fn ($n) => $n !== ''));
        }

        return inertia('Invoices/Index', [
            'invoices' => array_merge($invoices->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('invoices'),
            'filters' => [
                'number'         => $nums,
                'status'         => $request->get('status', []),
                'company_id'     => $request->get('company_id', []),
                'contact_id'     => $request->get('contact_id', []),
                'issue_from'     => $request->get('issue_from', ''),
                'issue_to'       => $request->get('issue_to', ''),
                'due_from'       => $request->get('due_from', ''),
                'due_to'         => $request->get('due_to', ''),
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'companyOptions' => $this->companyOptions(),
            'contactOptions' => $this->contactOptions(),
            'dealOptions'    => [],
            'statusOptions'  => $this->statusOptions(),
            'isSuper'        => $isSuper,
            'filterSchema'   => Invoice::filterSchema(),
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

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'items.product:id,name,sku',
            'payments.paymentMethod:id,name',
            'company:id,name,slug,legal_name,tax_id',
            'contact:id,name,primary_email,primary_phone,job_title',
            'owner:id,name,email',
            // Flow upstream: SalesOrder origen + Quote del SO.
            'salesOrder:id,slug,reference,quote_id',
            'salesOrder.quote:id,slug,reference',
        ]);
        return inertia('Invoices/Show', ['invoice' => $invoice]);
    }

    /**
     * Descarga el PDF de UNA factura individual (NO el export masivo del listado).
     * Generado on-demand con dompdf — sin job ni queue, devuelve la respuesta
     * directamente para que el browser abra el PDF inline.
     */
    public function showPdf(Invoice $invoice)
    {
        $invoice->load([
            'items.product:id,name,sku,description',
            'payments.paymentMethod:id,name',
            'company:id,name,slug,legal_name,tax_id,billing_email,website,domain',
            'contact:id,name,primary_email,primary_phone,job_title',
            'owner:id,name,email',
        ]);

        $tenant = request()->user()?->tenant;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('business_management.invoices.pdf', [
            'invoice' => $invoice,
            'tenant'  => $tenant,
        ])->setPaper('a4');

        $filename = ($invoice->number ?? 'invoice-' . $invoice->id) . '.pdf';

        return $pdf->stream($filename);
    }

    public function create()
    {
        return inertia('Invoices/Form', array_merge(['invoice' => null], $this->formOptions()));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('items');
        $payload = $invoice->only([
            'id','slug','number','prefix','reference','external_reference','document_type',
            'company_id','contact_id','owner_id','status',
            'issue_date','due_date','currency_code',
            'subtotal','discount_total','tax_total','shipping_cost','grand_total',
            'amount_paid','balance_due',
            'billing_legal_name','billing_tax_id','notes','internal_notes',
        ]);
        $payload['items'] = $invoice->items->map(fn ($i) => $i->only([
            'id','product_id','name','description','sku','quantity','unit_price','discount_pct','tax_pct',
            'line_subtotal','line_tax','line_total','sort_order',
        ]));
        return inertia('Invoices/Form', array_merge(['invoice' => $payload], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Invoice::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $invoice = Invoice::create($this->headerPayload($data, $request));
            $this->syncItems($invoice, $data['items'] ?? []);
            $this->recomputeTotals($invoice);
        });
        return redirect()->route('business_management.invoices.index')->with('success', __('invoices.created'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request, $invoice) {
            $invoice->update($this->headerPayload($data, $request, isUpdate: true));
            $this->syncItems($invoice, $data['items'] ?? []);
            $this->recomputeTotals($invoice);
        });
        return redirect()->route('business_management.invoices.show', $invoice->slug)->with('success', __('invoices.saved'));
    }

    // ── Delete flow Customer-style ──────────────────────────────────────

    public function delete(Invoice $invoice)
    {
        return inertia('Invoices/Delete', [
            'invoice' => $this->payload($invoice),
        ]);
    }

    public function deleteSave(DeleteInvoiceRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $service->delete($invoice, $request->validated()['deleted_description']);
        $this->storeUndoableDelete([$invoice->id]);

        return redirect()
            ->route('business_management.invoices.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$invoice->id]));
    }

    public function destroy(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $service->delete($invoice, __('global.quick_delete_reason'));
        return redirect()->route('business_management.invoices.index')->with('success', __('invoices.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['invoices.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    protected function buildRecentDeletePayload(array $ids): array
    {
        return ['count' => count($ids), 'seconds' => 60];
    }

    // ── Trash / Restore / Force delete (super only) ─────────────────────

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $number  = $request->get('number', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $invoices = Invoice::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($number !== '', fn ($q) => $q->where('number', 'like', "%{$number}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Invoices/Trash', [
            'invoices' => $invoices,
            'filters'  => [
                'number'   => $number,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, InvoiceService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Invoice::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.invoices.trash')
            ->with('success', __('global.restored_success'));
    }

    public function bulkRestore(BulkRestoreInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.invoices.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.invoices.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteInvoiceRequest $request, $slug, InvoiceService $service): RedirectResponse
    {
        $model = Invoice::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        $expected = $model->number ?? $model->reference ?? '';
        if (trim($data['name_confirmation']) !== $expected) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.invoices.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    // ── Edit All ────────────────────────────────────────────────────────

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $invoices = Invoice::query()
            ->filter($request)
            ->select('invoices.id', 'invoices.slug', 'invoices.number', 'invoices.status', 'invoices.grand_total', 'invoices.balance_due', 'invoices.currency_code', 'invoices.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Invoices/EditAll', [
            'invoices' => $invoices,
            'filters'  => [
                'number'    => $request->get('number', ''),
                'status'    => $request->get('status', ''),
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function editAllUpdate(EditAllUpdateInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.invoices.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    // ── Duplicate / Bulk / Undo ─────────────────────────────────────────

    public function duplicate(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $clone = $service->duplicate($invoice);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.invoices.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkDelete(BulkDeleteInvoiceRequest $request, InvoiceService $service): RedirectResponse
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

    public function bulkSetActive(BulkSetActiveInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    public function undoLastDelete(Request $request, InvoiceService $service): RedirectResponse
    {
        $claim = session('invoices.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('invoices.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('invoices.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('invoices.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateInvoicesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateInvoicesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateInvoicesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateInvoicesWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('invoices', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('invoices.export_limit_exceeded', [
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
            return Invoice::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Invoice::query()->filter($fakeReq)->count();
    }

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'number', 'reference', 'status', 'company', 'contact',
            'issue_date', 'due_date', 'currency_code',
            'subtotal', 'tax_total', 'grand_total', 'amount_paid', 'balance_due',
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
            'title'                   => $data['title']                   ?? __('invoices.export_title'),
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
            'auditable_type' => Invoice::class,
            'auditable_id'   => null,
            'module'         => 'invoices',
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
            'url'        => route('business_management.invoices.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ── IMPORTS ──────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Invoices\InvoicesImportTemplate(),
            __('invoices.import_template_filename')
        );
    }

    public function import(ImportInvoiceRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\Invoices\InvoicesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('InvoicesImport failed', [
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

    // ── Workflow action (preservada) ────────────────────────────────────

    public function cancel(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'refunded', 'cancelled'])) {
            return back()->with('error', __('invoices.cannot_cancel_in_state'));
        }
        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('cancellation_reason', __('invoices.cancelled_by_operator')),
        ]);
        return back()->with('success', __('invoices.cancelled'));
    }

    // ── Form options + helpers ──────────────────────────────────────────

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'companyOptions' => Company::orderBy('name')->limit(500)->get(['id','name','legal_name','tax_id'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'legal_name' => $c->legal_name, 'tax_id' => $c->tax_id])->all(),
            'contactOptions' => $this->contactOptions(),
            'productOptions' => Product::where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id','name','sku','list_price'])
                ->map(fn ($p) => [
                    'value' => $p->id, 'label' => $p->name . ($p->sku ? ' [' . $p->sku . ']' : ''),
                    'name' => $p->name, 'sku' => $p->sku, 'price' => (float) $p->list_price,
                ])->all(),
            'ownerOptions' => (function () use ($u) {
                $q = \App\Models\User::query();
                if ($u && !$u->hasRole('super') && $u->tenant_id) $q->where('tenant_id', $u->tenant_id);
                return $q->orderBy('name')->get(['id','name','email'])
                    ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name . ' (' . $x->email . ')'])->all();
            })(),
            'currencyOptions' => \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['code','symbol','name'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' — ' . $c->symbol . ' ' . $c->name])->all(),
            'statusOptions' => $this->statusOptions(),
            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
            'nextNumber' => $this->nextNumber($u?->tenant_id),
        ];
    }

    protected function companyOptions(): array
    {
        return Company::orderBy('name')->limit(500)->get(['id','name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }

    protected function contactOptions(): array
    {
        return Contact::orderBy('name')->limit(500)->get(['id','name','company_id'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'company_id' => $c->company_id])->all();
    }

    protected function statusOptions(): array
    {
        return collect(Invoice::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('invoices.status_options.' . $s)])->all();
    }

    protected function nextNumber(?int $tenantId): string
    {
        $year = Carbon::now()->year;
        $count = Invoice::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('FAC-%d-%05d', $year, $count);
    }

    protected function payload(Invoice $m): array
    {
        $m->loadMissing(['company:id,name,slug']);
        return [
            'id'             => $m->id,
            'slug'           => $m->slug,
            'number'         => $m->number,
            'company_id'     => $m->company_id,
            'company'        => $m->company ? ['id' => $m->company->id, 'name' => $m->company->name, 'slug' => $m->company->slug] : null,
            'status'         => $m->status,
            'issue_date'     => $m->issue_date,
            'due_date'       => $m->due_date,
            'currency_code'  => $m->currency_code,
            'grand_total'    => $m->grand_total,
            'amount_paid'    => $m->amount_paid,
            'balance_due'    => $m->balance_due,
            'created_at'     => $m->created_at,
            'updated_at'     => $m->updated_at,
            'deleted_at'     => $m->deleted_at,
        ];
    }

    // ── Validators + sync items (preservados) ───────────────────────────

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'number'             => ['nullable', 'string', 'max:40'],
            'reference'          => ['nullable', 'string', 'max:30'],
            'document_type'      => ['nullable', 'string', 'max:30'],
            'company_id'         => ['required', 'integer', 'exists:companies,id'],
            'contact_id'         => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id'           => ['nullable', 'integer', 'exists:users,id'],
            'status'             => ['required', Rule::in(Invoice::STATUSES)],
            'issue_date'         => ['required', 'date'],
            'due_date'           => ['required', 'date', 'after_or_equal:issue_date'],
            'currency_code'      => ['nullable', 'string', 'size:3'],
            'billing_legal_name' => ['nullable', 'string', 'max:200'],
            'billing_tax_id'     => ['nullable', 'string', 'max:50'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'internal_notes'     => ['nullable', 'string', 'max:2000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name'           => ['required', 'string', 'max:200'],
            'items.*.description'    => ['nullable', 'string', 'max:1000'],
            'items.*.sku'            => ['nullable', 'string', 'max:60'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['items.required' => __('invoices.items_required'), 'items.min' => __('invoices.items_required')]);
    }

    protected function headerPayload(array $data, Request $request, bool $isUpdate = false): array
    {
        $payload = collect($data)->only([
            'number','reference','document_type','company_id','contact_id','owner_id','status',
            'issue_date','due_date','currency_code','billing_legal_name','billing_tax_id','notes','internal_notes',
        ])->toArray();
        if (!$isUpdate) {
            $payload['tenant_id']  = $request->user()?->tenant_id;
            $payload['created_by'] = $request->user()?->id;
            $payload['prefix']     = 'FAC';
            $payload['amount_paid']= 0;
            $payload['balance_due']= 0;
            if (empty($payload['number'])) {
                $payload['number'] = $this->nextNumber($payload['tenant_id']);
            }
            if ($payload['status'] === 'sent') $payload['sent_at'] = now();
        }
        return $payload;
    }

    protected function syncItems(Invoice $invoice, array $items): void
    {
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        foreach ($items as $idx => $it) {
            $qty  = (float) $it['quantity'];
            $unit = (float) $it['unit_price'];
            $disc = (float) ($it['discount_pct'] ?? 0);
            $tax  = (float) ($it['tax_pct'] ?? 0);
            $sub  = round($qty * $unit * (1 - $disc / 100), 2);
            $taxA = round($sub * $tax / 100, 2);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $it['product_id'] ?? null,
                'name'       => $it['name'],
                'description'=> $it['description'] ?? null,
                'sku'        => $it['sku'] ?? null,
                'quantity'   => $qty, 'unit_price' => $unit,
                'discount_pct' => $disc, 'tax_pct' => $tax,
                'line_subtotal' => $sub, 'line_tax' => $taxA, 'line_total' => round($sub + $taxA, 2),
                'sort_order' => $idx,
            ]);
        }
    }

    protected function recomputeTotals(Invoice $invoice): void
    {
        $totals = InvoiceItem::where('invoice_id', $invoice->id)
            ->selectRaw('SUM(line_subtotal) as subtotal, SUM(line_tax) as tax_total, SUM(line_total) as grand_total')
            ->first();
        $grand = (float) ($totals->grand_total ?? 0);
        $paid  = (float) $invoice->amount_paid;
        $invoice->update([
            'subtotal'    => $totals->subtotal ?? 0,
            'tax_total'   => $totals->tax_total ?? 0,
            'grand_total' => $grand,
            'balance_due' => max(0, round($grand - $paid, 2)),
        ]);
    }
}
