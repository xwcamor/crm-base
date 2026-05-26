<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Quote\BulkDeleteQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\BulkRestoreQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\BulkSetActiveQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\DeleteQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\EditAllUpdateQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\ForceDeleteQuoteRequest;
use App\Http\Requests\BusinessManagement\Quote\ImportQuoteRequest;
use App\Jobs\BusinessManagement\Quotes\GenerateQuotesCsvJob;
use App\Jobs\BusinessManagement\Quotes\GenerateQuotesExcelJob;
use App\Jobs\BusinessManagement\Quotes\GenerateQuotesPdfJob;
use App\Jobs\BusinessManagement\Quotes\GenerateQuotesWordJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\BusinessManagement\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = ['creator:id,name,email', 'company:id,name,slug', 'contact:id,name,slug', 'owner:id,name,email', 'deal:id,name,slug'];
        if ($isSuper) $with[] = 'tenant:id,name';

        $query = Quote::query()
            ->select('quotes.*')
            ->with($with)
            ->filter($request);

        $quotes = $query->paginate($perPage)->withQueryString();
        $totalUnfiltered = Quote::count();

        $refs = $request->get('reference', '');
        if (is_array($refs)) {
            $refs = array_values(array_filter($refs, fn ($r) => $r !== ''));
        }

        return inertia('Quotes/Index', [
            'quotes' => array_merge($quotes->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('quotes'),
            'filters' => [
                'reference'      => $refs,
                'status'         => $request->get('status', []),
                'company_id'     => $request->get('company_id', []),
                'contact_id'     => $request->get('contact_id', []),
                'deal_id'        => $request->get('deal_id', []),
                'issue_from'     => $request->get('issue_from', ''),
                'issue_to'       => $request->get('issue_to', ''),
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
            'dealOptions'    => $this->dealOptions(),
            'statusOptions'  => $this->statusOptions(),
            'isSuper'        => $isSuper,
            'filterSchema'   => Quote::filterSchema(),
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

    public function show(Quote $quote)
    {
        $quote->load([
            'items.product:id,name,sku',
            'company:id,name,slug,legal_name,tax_id',
            'contact:id,name,primary_email,primary_phone,job_title',
            'owner:id,name,email',
            'creator:id,name,email',
            'deal:id,name,slug',
        ]);
        return inertia('Quotes/Show', ['quote' => $quote]);
    }

    /**
     * PDF individual de la cotizacion. Stream inline para abrir en el browser.
     */
    public function showPdf(Quote $quote)
    {
        $quote->load([
            'items.product:id,name,sku,description',
            'company:id,name,slug,legal_name,tax_id,billing_email,website',
            'contact:id,name,primary_email,primary_phone,job_title',
            'owner:id,name,email',
        ]);

        $tenant = request()->user()?->tenant;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('business_management.quotes.pdf', [
            'quote'  => $quote,
            'tenant' => $tenant,
        ])->setPaper('a4');

        $filename = ($quote->reference ?? 'quote-' . $quote->id) . '.pdf';
        return $pdf->stream($filename);
    }

    public function create(Request $request)
    {
        // Pre-fill cuando se viene desde Deal Show con "Crear cotizacion".
        $defaults = null;
        if ($request->filled('deal_id')) {
            $deal = Deal::find($request->get('deal_id'));
            if ($deal && $deal->tenant_id === auth()->user()?->tenant_id) {
                $defaults = [
                    'deal_id'       => $deal->id,
                    'company_id'    => $deal->company_id,
                    'contact_id'    => $deal->contact_id,
                    'currency_code' => $deal->currency_code,
                ];
            }
        }

        return inertia('Quotes/Form', array_merge(
            ['quote' => $defaults],
            $this->formOptions()
        ));
    }

    public function edit(Quote $quote)
    {
        $quote->load('items');
        $payload = $quote->only([
            'id','slug','reference','prefix','external_reference','status',
            'company_id','contact_id','owner_id','deal_id',
            'issue_date','valid_until','currency_code',
            'subtotal','discount_total','tax_total','shipping_cost','grand_total',
            'notes','internal_notes',
        ]);
        $payload['items'] = $quote->items->map(fn ($i) => $i->only([
            'id','product_id','name','description','sku','quantity','unit_price','discount_pct','tax_pct',
            'line_subtotal','line_tax','line_total','sort_order',
        ]));
        return inertia('Quotes/Form', array_merge(['quote' => $payload], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Quote::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $quote = Quote::create($this->headerPayload($data, $request));
            $this->syncItems($quote, $data['items'] ?? []);
            $this->recomputeTotals($quote);
        });
        return redirect()->route('business_management.quotes.index')->with('success', __('quotes.created'));
    }

    public function update(Request $request, Quote $quote)
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request, $quote) {
            $quote->update($this->headerPayload($data, $request, isUpdate: true));
            $this->syncItems($quote, $data['items'] ?? []);
            $this->recomputeTotals($quote);
        });
        return redirect()->route('business_management.quotes.show', $quote->slug)->with('success', __('quotes.saved'));
    }

    // ── Delete flow Customer-style (delete confirmation + deleteSave + destroy) ─

    public function delete(Quote $quote)
    {
        return inertia('Quotes/Delete', [
            'quote' => $this->payload($quote),
        ]);
    }

    public function deleteSave(DeleteQuoteRequest $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $service->delete($quote, $request->validated()['deleted_description']);
        $this->storeUndoableDelete([$quote->id]);

        return redirect()
            ->route('business_management.quotes.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$quote->id]));
    }

    public function destroy(Quote $quote, QuoteService $service): RedirectResponse
    {
        $service->delete($quote, __('global.quick_delete_reason'));
        return redirect()->route('business_management.quotes.index')->with('success', __('quotes.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['quotes.recent_delete' => [
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

        $reference = $request->get('reference', '');
        $perPage   = (int) $request->get('per_page', 25);
        $perPage   = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $quotes = Quote::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Quotes/Trash', [
            'quotes'  => $quotes,
            'filters' => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, QuoteService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Quote::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.quotes.trash')
            ->with('success', __('global.restored_success'));
    }

    public function bulkRestore(BulkRestoreQuoteRequest $request, QuoteService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.quotes.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.quotes.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteQuoteRequest $request, $slug, QuoteService $service): RedirectResponse
    {
        $model = Quote::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        $expected = $model->reference ?? $model->name ?? '';
        if (trim($data['name_confirmation']) !== $expected) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.quotes.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    // ── Edit All ────────────────────────────────────────────────────────

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $quotes = Quote::query()
            ->filter($request)
            ->select('quotes.id', 'quotes.slug', 'quotes.reference', 'quotes.status', 'quotes.grand_total', 'quotes.currency_code', 'quotes.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Quotes/EditAll', [
            'quotes'  => $quotes,
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

    public function editAllUpdate(EditAllUpdateQuoteRequest $request, QuoteService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.quotes.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    // ── Duplicate / Bulk / Undo ─────────────────────────────────────────

    public function duplicate(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $clone = $service->duplicate($quote);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.quotes.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkDelete(BulkDeleteQuoteRequest $request, QuoteService $service): RedirectResponse
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

    public function bulkSetActive(BulkSetActiveQuoteRequest $request, QuoteService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    public function undoLastDelete(Request $request, QuoteService $service): RedirectResponse
    {
        $claim = session('quotes.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('quotes.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('quotes.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('quotes.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateQuotesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateQuotesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateQuotesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateQuotesWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('quotes', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('quotes.export_limit_exceeded', [
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
            return Quote::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Quote::query()->filter($fakeReq)->count();
    }

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'reference', 'status', 'company', 'contact',
            'issue_date', 'valid_until', 'currency_code',
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
            'title'                   => $data['title']                   ?? __('quotes.export_title'),
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
            'auditable_type' => Quote::class,
            'auditable_id'   => null,
            'module'         => 'quotes',
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
            'url'        => route('business_management.quotes.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ── IMPORTS ──────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Quotes\QuotesImportTemplate(),
            __('quotes.import_template_filename')
        );
    }

    public function import(ImportQuoteRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\Quotes\QuotesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('QuotesImport failed', [
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

    // ── Workflow actions (preservadas del controller original) ──────────

    public function send(Quote $quote)
    {
        if ($quote->status !== 'draft') return back()->with('error', __('quotes.only_drafts_can_be_sent'));
        $quote->update(['status' => 'sent', 'sent_at' => now()]);
        return back()->with('success', __('quotes.marked_as_sent'));
    }

    public function accept(Request $request, Quote $quote)
    {
        if (!in_array($quote->status, ['sent', 'draft'])) return back()->with('error', __('quotes.cannot_accept_in_state'));
        $quote->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'signed_by_name'  => $request->input('signed_by_name'),
            'signed_by_email' => $request->input('signed_by_email'),
        ]);
        return back()->with('success', __('quotes.accepted'));
    }

    public function reject(Request $request, Quote $quote)
    {
        if (!in_array($quote->status, ['sent', 'draft'])) return back()->with('error', __('quotes.cannot_reject_in_state'));
        $quote->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_reason' => $request->input('rejected_reason', __('quotes.rejected_no_reason')),
        ]);
        return back()->with('success', __('quotes.rejected'));
    }

    // ── Conversiones (preservadas) ──────────────────────────────────────

    public function convertToInvoice(Quote $quote)
    {
        if ($quote->status !== 'accepted') return back()->with('error', __('quotes.only_accepted_can_invoice'));
        $quote->load('items', 'company');

        $invoice = null;
        DB::transaction(function () use ($quote, &$invoice) {
            $count = Invoice::where('tenant_id', $quote->tenant_id)->whereYear('created_at', now()->year)->count() + 1;
            $invoice = Invoice::create([
                'number'        => sprintf('FAC-%d-%05d', now()->year, $count),
                'prefix'        => 'FAC',
                'reference'     => 'desde-' . $quote->reference,
                'company_id'    => $quote->company_id,
                'contact_id'    => $quote->contact_id,
                'status'        => 'sent',
                'issue_date'    => now()->toDateString(),
                'due_date'      => now()->addDays(30)->toDateString(),
                'sent_at'       => now(),
                'currency_code' => $quote->currency_code,
                'subtotal'      => $quote->subtotal,
                'tax_total'     => $quote->tax_total,
                'discount_total'=> $quote->discount_total,
                'shipping_cost' => $quote->shipping_cost,
                'grand_total'   => $quote->grand_total,
                'amount_paid'   => 0,
                'balance_due'   => $quote->grand_total,
                'billing_legal_name' => $quote->company->legal_name ?? $quote->company->name,
                'billing_tax_id'     => $quote->company->tax_id,
                'notes'         => __('quotes.generated_from_quote', ['reference' => $quote->reference]),
                'tenant_id'     => $quote->tenant_id,
                'owner_id'      => $quote->owner_id,
                'created_by'    => auth()->id(),
            ]);
            foreach ($quote->items as $it) {
                InvoiceItem::create([
                    'invoice_id'    => $invoice->id,
                    'product_id'    => $it->product_id,
                    'name'          => $it->name,
                    'description'   => $it->description,
                    'sku'           => $it->sku,
                    'quantity'      => $it->quantity,
                    'unit_price'    => $it->unit_price,
                    'discount_pct'  => $it->discount_pct,
                    'tax_pct'       => $it->tax_pct,
                    'line_subtotal' => $it->line_subtotal,
                    'line_tax'      => $it->line_tax,
                    'line_total'    => $it->line_total,
                    'sort_order'    => $it->sort_order,
                ]);
            }
        });

        return redirect()->route('business_management.invoices.show', $invoice->slug)
            ->with('success', __('quotes.invoice_generated', ['number' => $invoice->number]));
    }

    public function convertToSalesOrder(Quote $quote)
    {
        if ($quote->status !== 'accepted') return back()->with('error', __('quotes.only_accepted_can_so'));
        $quote->load('items');

        $defaultWh = \App\Models\Warehouse::where('tenant_id', $quote->tenant_id)
            ->where('is_default', true)->where('is_active', true)->first()
            ?? \App\Models\Warehouse::where('tenant_id', $quote->tenant_id)->where('is_active', true)->first();
        if (!$defaultWh) return back()->with('error', __('quotes.no_active_warehouse'));

        $order = null;
        DB::transaction(function () use ($quote, $defaultWh, &$order) {
            $count = SalesOrder::where('tenant_id', $quote->tenant_id)->whereYear('created_at', now()->year)->count() + 1;
            $order = SalesOrder::create([
                'prefix'         => 'OV',
                'reference'      => sprintf('OV-%d-%04d', now()->year, $count),
                'quote_id'       => $quote->id,
                'company_id'     => $quote->company_id,
                'contact_id'     => $quote->contact_id,
                'status'         => 'pending',
                'warehouse_id'   => $defaultWh->id,
                'order_date'     => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'currency_code'  => $quote->currency_code,
                'subtotal'       => $quote->subtotal,
                'discount_total' => $quote->discount_total,
                'tax_total'      => $quote->tax_total,
                'shipping_cost'  => $quote->shipping_cost,
                'grand_total'    => $quote->grand_total,
                'payment_terms_days' => 30,
                'payment_status' => 'unpaid',
                'notes'          => __('quotes.generated_from_quote', ['reference' => $quote->reference]),
                'tenant_id'      => $quote->tenant_id,
                'owner_id'       => $quote->owner_id,
                'created_by'     => auth()->id(),
            ]);
            foreach ($quote->items as $it) {
                SalesOrderItem::create([
                    'sales_order_id'     => $order->id,
                    'product_id'         => $it->product_id,
                    'name'               => $it->name,
                    'sku'                => $it->sku,
                    'quantity_ordered'   => $it->quantity,
                    'quantity_fulfilled' => 0,
                    'unit_price'         => $it->unit_price,
                    'discount_pct'       => $it->discount_pct,
                    'tax_pct'            => $it->tax_pct,
                    'line_subtotal'      => $it->line_subtotal,
                    'line_tax'           => $it->line_tax,
                    'line_total'         => $it->line_total,
                    'sort_order'         => $it->sort_order,
                ]);
            }
        });

        return redirect()->route('business_management.sales_orders.show', $order->slug)
            ->with('success', __('quotes.sales_order_generated', ['reference' => $order->reference]));
    }

    // ── Form options + helpers ──────────────────────────────────────────

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'companyOptions' => $this->companyOptions(),
            'contactOptions' => $this->contactOptions(),
            'dealOptions'    => $this->dealOptions(),
            'productOptions' => Product::where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id','name','sku','list_price','currency_code'])
                ->map(fn ($p) => [
                    'value' => $p->id, 'label' => $p->name . ($p->sku ? ' [' . $p->sku . ']' : ''),
                    'name' => $p->name, 'sku' => $p->sku,
                    'price' => (float) $p->list_price, 'currency' => $p->currency_code,
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
            'nextReference' => $this->nextReference($u?->tenant_id),
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

    protected function dealOptions(): array
    {
        return Deal::where('status', 'open')->orderBy('name')->limit(200)->get(['id','name','company_id'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name, 'company_id' => $d->company_id])->all();
    }

    protected function statusOptions(): array
    {
        return collect(Quote::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('quotes.status_options.' . $s)])->all();
    }

    protected function nextReference(?int $tenantId): string
    {
        $year = Carbon::now()->year;
        $count = Quote::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)->count() + 1;
        return sprintf('COT-%d-%04d', $year, $count);
    }

    protected function payload(Quote $m): array
    {
        $m->loadMissing(['company:id,name,slug']);
        return [
            'id'             => $m->id,
            'slug'           => $m->slug,
            'reference'      => $m->reference,
            'company_id'     => $m->company_id,
            'company'        => $m->company ? ['id' => $m->company->id, 'name' => $m->company->name, 'slug' => $m->company->slug] : null,
            'status'         => $m->status,
            'issue_date'     => $m->issue_date,
            'valid_until'    => $m->valid_until,
            'currency_code'  => $m->currency_code,
            'grand_total'    => $m->grand_total,
            'created_at'     => $m->created_at,
            'updated_at'     => $m->updated_at,
            'deleted_at'     => $m->deleted_at,
        ];
    }

    // ── Validators + sync items (preservados) ───────────────────────────

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'reference'         => ['nullable', 'string', 'max:30'],
            'company_id'        => ['required', 'integer', 'exists:companies,id'],
            'contact_id'        => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id'          => ['nullable', 'integer', 'exists:users,id'],
            'deal_id'           => ['nullable', 'integer', 'exists:deals,id'],
            'status'            => ['required', Rule::in(Quote::STATUSES)],
            'issue_date'        => ['required', 'date'],
            'valid_until'       => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_code'     => ['nullable', 'string', 'size:3'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'internal_notes'    => ['nullable', 'string', 'max:2000'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name'           => ['required', 'string', 'max:200'],
            'items.*.description'    => ['nullable', 'string', 'max:1000'],
            'items.*.sku'            => ['nullable', 'string', 'max:60'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'items.required' => __('quotes.items_required'),
            'items.min'      => __('quotes.items_required'),
        ]);
    }

    protected function headerPayload(array $data, Request $request, bool $isUpdate = false): array
    {
        $payload = collect($data)->only([
            'reference','company_id','contact_id','owner_id','deal_id',
            'status','issue_date','valid_until','currency_code','notes','internal_notes',
        ])->toArray();
        if (!$isUpdate) {
            $payload['tenant_id']  = $request->user()?->tenant_id;
            $payload['created_by'] = $request->user()?->id;
            $payload['prefix']     = 'COT';
            if (empty($payload['reference'])) {
                $payload['reference'] = $this->nextReference($payload['tenant_id']);
            }
        }
        return $payload;
    }

    protected function syncItems(Quote $quote, array $items): void
    {
        QuoteItem::where('quote_id', $quote->id)->delete();
        foreach ($items as $idx => $it) {
            $qty   = (float) $it['quantity'];
            $unit  = (float) $it['unit_price'];
            $disc  = (float) ($it['discount_pct'] ?? 0);
            $tax   = (float) ($it['tax_pct']      ?? 0);
            $lineSub   = round($qty * $unit * (1 - $disc / 100), 2);
            $lineTax   = round($lineSub * $tax / 100, 2);
            $lineTotal = round($lineSub + $lineTax, 2);
            QuoteItem::create([
                'quote_id'      => $quote->id,
                'product_id'    => $it['product_id'] ?? null,
                'name'          => $it['name'],
                'description'   => $it['description'] ?? null,
                'sku'           => $it['sku'] ?? null,
                'quantity'      => $qty,
                'unit_price'    => $unit,
                'discount_pct'  => $disc,
                'tax_pct'       => $tax,
                'line_subtotal' => $lineSub,
                'line_tax'      => $lineTax,
                'line_total'    => $lineTotal,
                'sort_order'    => $idx,
            ]);
        }
    }

    protected function recomputeTotals(Quote $quote): void
    {
        $totals = QuoteItem::where('quote_id', $quote->id)
            ->selectRaw('SUM(line_subtotal) as subtotal, SUM(line_tax) as tax_total, SUM(line_total) as grand_total')
            ->first();
        $quote->update([
            'subtotal'    => $totals->subtotal ?? 0,
            'tax_total'   => $totals->tax_total ?? 0,
            'grand_total' => $totals->grand_total ?? 0,
        ]);
    }
}
