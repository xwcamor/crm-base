<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\Payment\BulkDeletePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\BulkRestorePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\BulkSetActivePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\DeletePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\EditAllUpdatePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\ForceDeletePaymentRequest;
use App\Http\Requests\BusinessManagement\Payment\ImportPaymentRequest;
use App\Jobs\BusinessManagement\Payments\GeneratePaymentsCsvJob;
use App\Jobs\BusinessManagement\Payments\GeneratePaymentsExcelJob;
use App\Jobs\BusinessManagement\Payments\GeneratePaymentsPdfJob;
use App\Jobs\BusinessManagement\Payments\GeneratePaymentsWordJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\BusinessManagement\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = [
            'creator:id,name,email',
            'company:id,name,slug',
            'invoice:id,number,grand_total,balance_due,slug',
            'paymentMethod:id,name',
        ];
        if ($isSuper) $with[] = 'tenant:id,name';

        $query = Payment::query()
            ->select('payments.*')
            ->with($with)
            ->filter($request);

        $payments = $query->paginate($perPage)->withQueryString();
        $totalUnfiltered = Payment::count();

        $refs = $request->get('reference', '');
        if (is_array($refs)) {
            $refs = array_values(array_filter($refs, fn ($r) => $r !== ''));
        }

        return inertia('Payments/Index', [
            'payments' => array_merge($payments->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('payments'),
            'filters' => [
                'reference'         => $refs,
                'status'            => $request->get('status', []),
                'type'              => $request->get('type', []),
                'payment_method_id' => $request->get('payment_method_id', []),
                'company_id'        => $request->get('company_id', []),
                'invoice_id'        => $request->get('invoice_id', []),
                'paid_from'         => $request->get('paid_from', ''),
                'paid_to'           => $request->get('paid_to', ''),
                'created_from'      => $request->get('created_from', ''),
                'created_to'        => $request->get('created_to', ''),
                'only_favorites'    => $request->boolean('only_favorites'),
                'sort'              => $request->get('sort', 'id'),
                'direction'         => $request->get('direction', 'desc'),
                'per_page'          => $perPage,
                'advanced_where'    => $this->parseAdvancedWhere($request),
            ],
            'companyOptions' => $this->companyOptions(),
            'invoiceOptions' => $this->invoiceOptionsLite(),
            'statusOptions'  => $this->statusOptions(),
            'typeOptions'    => $this->typeOptions(),
            'methodOptions'  => $this->methodOptions(),
            'isSuper'        => $isSuper,
            'filterSchema'   => Payment::filterSchema(),
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

    public function show(Payment $payment)
    {
        $payment->load([
            'company:id,name,legal_name',
            'invoice:id,slug,number,grand_total,balance_due,status',
            'paymentMethod:id,name,code',
        ]);
        return inertia('Payments/Show', ['payment' => $payment]);
    }

    public function create(Request $request)
    {
        return inertia('Payments/Form', array_merge(
            ['payment' => null, 'preselectedInvoiceId' => (int) $request->get('invoice_id', 0) ?: null],
            $this->formOptions()
        ));
    }

    public function edit(Payment $payment)
    {
        $payload = $payment->only([
            'id','slug','reference','company_id','invoice_id','type','payment_method_id',
            'amount','currency_code','exchange_rate','paid_at','reconciled_at',
            'external_transaction_id','bank_reference','notes','status',
        ]);
        return inertia('Payments/Form', array_merge(['payment' => $payload], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Payment::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $payment = Payment::create(array_merge($data, [
                'tenant_id'  => $request->user()?->tenant_id,
                'created_by' => $request->user()?->id,
                'reference'  => $data['reference'] ?: $this->nextReference($request->user()?->tenant_id),
            ]));
            $this->applyToInvoice($payment);
        });
        return redirect()->route('business_management.payments.index')->with('success', __('payments.created'));
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $payment) {
            $oldInvoiceId = $payment->invoice_id;
            $payment->update($data);
            if ($oldInvoiceId && $oldInvoiceId !== $payment->invoice_id) {
                $this->recomputeInvoice(Invoice::find($oldInvoiceId));
            }
            $this->applyToInvoice($payment);
        });
        return redirect()->route('business_management.payments.show', $payment->slug)->with('success', __('payments.saved'));
    }

    // ── Delete flow Customer-style ──────────────────────────────────────

    public function delete(Payment $payment)
    {
        return inertia('Payments/Delete', [
            'payment' => $this->payload($payment),
        ]);
    }

    public function deleteSave(DeletePaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        DB::transaction(function () use ($payment, $request, $service) {
            $invoiceId = $payment->invoice_id;
            $service->delete($payment, $request->validated()['deleted_description']);
            if ($invoiceId) $this->recomputeInvoice(Invoice::find($invoiceId));
        });
        $this->storeUndoableDelete([$payment->id]);

        return redirect()
            ->route('business_management.payments.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$payment->id]));
    }

    public function destroy(Payment $payment, PaymentService $service): RedirectResponse
    {
        DB::transaction(function () use ($payment, $service) {
            $invoiceId = $payment->invoice_id;
            $service->delete($payment, __('global.quick_delete_reason'));
            if ($invoiceId) $this->recomputeInvoice(Invoice::find($invoiceId));
        });
        return redirect()->route('business_management.payments.index')->with('success', __('payments.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['payments.recent_delete' => [
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

        $payments = Payment::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Payments/Trash', [
            'payments' => $payments,
            'filters'  => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Payment::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);
        if ($model->invoice_id) $this->recomputeInvoice(Invoice::find($model->invoice_id));

        return redirect()
            ->route('business_management.payments.trash')
            ->with('success', __('global.restored_success'));
    }

    public function bulkRestore(BulkRestorePaymentRequest $request, PaymentService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.payments.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.payments.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeletePaymentRequest $request, $slug, PaymentService $service): RedirectResponse
    {
        $model = Payment::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        $expected = $model->reference ?? $model->name ?? '';
        if (trim($data['name_confirmation']) !== $expected) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.payments.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    // ── Edit All ────────────────────────────────────────────────────────

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $payments = Payment::query()
            ->filter($request)
            ->select('payments.id', 'payments.slug', 'payments.reference', 'payments.status', 'payments.amount', 'payments.currency_code', 'payments.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Payments/EditAll', [
            'payments' => $payments,
            'filters'  => [
                'reference' => $request->get('reference', ''),
                'status'    => $request->get('status', ''),
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function editAllUpdate(EditAllUpdatePaymentRequest $request, PaymentService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.payments.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    // ── Duplicate / Bulk / Undo ─────────────────────────────────────────

    public function duplicate(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $clone = $service->duplicate($payment);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.payments.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkDelete(BulkDeletePaymentRequest $request, PaymentService $service): RedirectResponse
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

    public function bulkSetActive(BulkSetActivePaymentRequest $request, PaymentService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    public function undoLastDelete(Request $request, PaymentService $service): RedirectResponse
    {
        $claim = session('payments.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('payments.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('payments.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('payments.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GeneratePaymentsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GeneratePaymentsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GeneratePaymentsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GeneratePaymentsWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('payments', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('payments.export_limit_exceeded', [
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
            return Payment::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Payment::query()->filter($fakeReq)->count();
    }

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'reference', 'company', 'invoice', 'payment_method', 'type',
            'amount', 'currency_code', 'status', 'paid_at',
            'bank_reference', 'external_transaction_id',
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
            'title'                   => $data['title']                   ?? __('payments.export_title'),
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
            'auditable_type' => Payment::class,
            'auditable_id'   => null,
            'module'         => 'payments',
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
            'url'        => route('business_management.payments.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ── IMPORTS ──────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\Payments\PaymentsImportTemplate(),
            __('payments.import_template_filename')
        );
    }

    public function import(ImportPaymentRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\Payments\PaymentsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('PaymentsImport failed', [
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

    // ── Form options + helpers ──────────────────────────────────────────

    protected function formOptions(): array
    {
        return [
            'invoiceOptions' => Invoice::whereIn('status', ['sent','partial','overdue'])
                ->where('balance_due', '>', 0)
                ->with('company:id,name')
                ->orderBy('issue_date', 'desc')->limit(500)->get(['id','number','company_id','grand_total','balance_due','currency_code'])
                ->map(fn ($i) => [
                    'value' => $i->id,
                    'label' => $i->number . ' — ' . ($i->company?->name ?? '?') . ' (saldo: ' . $i->currency_code . ' ' . number_format($i->balance_due, 2) . ')',
                    'company_id' => $i->company_id,
                    'balance_due' => (float) $i->balance_due,
                    'currency_code' => $i->currency_code,
                ])->all(),
            'paymentMethodOptions' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id','name','requires_reference'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name, 'requires_reference' => (bool) $m->requires_reference])->all(),
            'typeOptions'    => $this->typeOptions(),
            'statusOptions'  => $this->statusOptions(),
            'methodOptions'  => $this->methodOptions(),
            'companyOptions' => $this->companyOptions(),
            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
            'nextReference' => $this->nextReference(auth()->user()?->tenant_id),
        ];
    }

    protected function companyOptions(): array
    {
        return Company::orderBy('name')->limit(500)->get(['id','name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }

    protected function invoiceOptionsLite(): array
    {
        return Invoice::orderBy('issue_date', 'desc')->limit(500)->get(['id','number','company_id','grand_total','balance_due','currency_code'])
            ->map(fn ($i) => ['value' => $i->id, 'label' => $i->number])->all();
    }

    protected function statusOptions(): array
    {
        return collect(Payment::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('payments.status_options.' . $s)])->all();
    }

    protected function typeOptions(): array
    {
        return collect(Payment::TYPES)
            ->map(fn ($t) => ['value' => $t, 'label' => __('payments.type_options.' . $t)])->all();
    }

    protected function methodOptions(): array
    {
        return PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all();
    }

    protected function nextReference(?int $tenantId): string
    {
        $year = Carbon::now()->year;
        $count = Payment::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('PAGO-%d-%05d', $year, $count);
    }

    protected function payload(Payment $m): array
    {
        $m->loadMissing(['company:id,name,slug', 'invoice:id,number,slug']);
        return [
            'id'             => $m->id,
            'slug'           => $m->slug,
            'reference'      => $m->reference,
            'company_id'     => $m->company_id,
            'company'        => $m->company ? ['id' => $m->company->id, 'name' => $m->company->name, 'slug' => $m->company->slug] : null,
            'invoice'        => $m->invoice ? ['id' => $m->invoice->id, 'number' => $m->invoice->number, 'slug' => $m->invoice->slug] : null,
            'status'         => $m->status,
            'type'           => $m->type,
            'amount'         => $m->amount,
            'currency_code'  => $m->currency_code,
            'paid_at'        => $m->paid_at,
            'created_at'     => $m->created_at,
            'updated_at'     => $m->updated_at,
            'deleted_at'     => $m->deleted_at,
        ];
    }

    // ── Apply to invoice + validators (preservados) ─────────────────────

    protected function applyToInvoice(Payment $payment): void
    {
        if (!$payment->invoice_id) return;
        $this->recomputeInvoice($payment->invoice);
    }

    protected function recomputeInvoice(?Invoice $invoice): void
    {
        if (!$invoice) return;
        $totalPaid = (float) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'completed')->sum('amount');
        $balance = max(0, round((float) $invoice->grand_total - $totalPaid, 2));
        $newStatus = $invoice->status;
        if ($balance <= 0) {
            $newStatus = 'paid';
            $invoice->paid_at = $invoice->paid_at ?? now();
        } elseif ($totalPaid > 0 && $balance > 0) {
            $newStatus = 'partial';
        } elseif ($totalPaid === 0.0) {
            $newStatus = in_array($invoice->status, ['paid', 'partial']) ? 'sent' : $invoice->status;
            $invoice->paid_at = null;
        }
        $invoice->amount_paid = round($totalPaid, 2);
        $invoice->balance_due = $balance;
        $invoice->status = $newStatus;
        $invoice->save();
    }

    protected function validateData(Request $request): array
    {
        $tenantId  = $request->user()?->tenant_id;
        $paymentId = $request->route('payment')?->id;

        return $request->validate([
            // reference unico por tenant (case insensitive). El indice partial
            // unique en BD es el ultimo guardrail; esta validacion lo detecta
            // antes con un mensaje amigable.
            'reference' => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId, $paymentId) {
                    if (blank($value)) return;
                    $needle = trim((string) $value);
                    $exists = DB::table('payments')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($paymentId, fn ($q) => $q->where('id', '!=', $paymentId))
                        ->whereRaw('LOWER(reference) = LOWER(?)', [$needle])
                        ->exists();
                    if ($exists) {
                        $fail(__('payments.reference_unique'));
                    }
                },
            ],
            'company_id'             => ['nullable', 'integer', 'exists:companies,id'],
            'invoice_id'             => ['nullable', 'integer', 'exists:invoices,id'],
            'type'                   => ['required', Rule::in(Payment::TYPES)],
            'payment_method_id'      => ['required', 'integer', 'exists:payment_methods,id'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'paid_at'                => ['required', 'date'],
            'status'                 => ['required', Rule::in(Payment::STATUSES)],
            'bank_reference'         => ['nullable', 'string', 'max:100'],
            'external_transaction_id'=> ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
