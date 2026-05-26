<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\ExchangeRate\BulkDeleteExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\BulkRestoreExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\BulkSetActiveExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\DeleteExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\EditAllUpdateExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\ForceDeleteExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\ImportExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\StoreExchangeRateRequest;
use App\Http\Requests\BusinessManagement\ExchangeRate\UpdateExchangeRateRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\ExchangeRates\GenerateExchangeRatesCsvJob;
use App\Jobs\BusinessManagement\ExchangeRates\GenerateExchangeRatesExcelJob;
use App\Jobs\BusinessManagement\ExchangeRates\GenerateExchangeRatesPdfJob;
use App\Jobs\BusinessManagement\ExchangeRates\GenerateExchangeRatesWordJob;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\BusinessManagement\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200], true) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'valid_at', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        $with = ['creator:id,name,email'];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $rates = ExchangeRate::query()
            ->select('exchange_rates.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = ExchangeRate::count();

        $bases = $request->get('base_code', []);
        if (is_string($bases)) $bases = $bases === '' ? [] : [$bases];
        $quotes = $request->get('quote_code', []);
        if (is_string($quotes)) $quotes = $quotes === '' ? [] : [$quotes];

        return inertia('ExchangeRates/Index', [
            'rates' => array_merge($rates->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('exchange_rates'),
            'filters' => [
                'base_code'     => array_values($bases),
                'quote_code'    => array_values($quotes),
                'source'        => $request->get('source', ''),
                'is_active'     => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'valid_from'    => $request->get('valid_from', ''),
                'valid_to'      => $request->get('valid_to', ''),
                'created_from'  => $request->get('created_from', ''),
                'created_to'    => $request->get('created_to', ''),
                'only_favorites'=> $request->boolean('only_favorites'),
                'sort'          => $request->get('sort', 'valid_at'),
                'direction'     => $request->get('direction', 'desc'),
                'per_page'      => $perPage,
                'advanced_where'=> $this->parseAdvancedWhere($request),
            ],
            'currencyOptions' => $this->currencyOptions(),
            'isSuper'         => $isSuper,
            'filterSchema'    => ExchangeRate::filterSchema(),
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

    public function show(Request $request, ExchangeRate $exchange_rate)
    {
        $exchange_rate->load(['creator:id,name,email', 'deleter:id,name,email']);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', ExchangeRate::class)
                    ->where('auditable_id', $exchange_rate->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('ExchangeRates/Show', [
            'rate'     => $this->payload($exchange_rate, withAudit: true),
            'activity' => $activity,
        ]);
    }

    public function create()
    {
        return inertia('ExchangeRates/Form', array_merge(
            ['rate' => null],
            $this->formSelectOptions()
        ));
    }

    protected function formSelectOptions(): array
    {
        return [
            'currencyOptions' => $this->currencyOptions(),
        ];
    }

    protected function currencyOptions(): array
    {
        return Currency::where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' — ' . $c->name])
            ->all();
    }

    public function store(StoreExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && ExchangeRate::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('business_management.exchange_rates.index')
            ->with('success', __('exchange_rates.created'));
    }

    public function edit(ExchangeRate $exchange_rate)
    {
        return inertia('ExchangeRates/Form', array_merge(
            ['rate' => $this->payload($exchange_rate)],
            $this->formSelectOptions()
        ));
    }

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchange_rate, ExchangeRateService $service): RedirectResponse
    {
        $service->update($exchange_rate, $request->validated());

        return redirect()
            ->route('business_management.exchange_rates.index')
            ->with('success', __('exchange_rates.saved'));
    }

    public function delete(ExchangeRate $exchange_rate)
    {
        return inertia('ExchangeRates/Delete', [
            'rate' => $this->payload($exchange_rate),
        ]);
    }

    public function deleteSave(DeleteExchangeRateRequest $request, ExchangeRate $exchange_rate, ExchangeRateService $service): RedirectResponse
    {
        $service->delete($exchange_rate, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$exchange_rate->id]);

        return redirect()
            ->route('business_management.exchange_rates.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$exchange_rate->id]));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['exchange_rates.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => 60,
        ];
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $term    = $request->get('term', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $rates = ExchangeRate::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($term !== '', fn ($q) => $q->where(function ($qq) use ($term) {
                $qq->where('base_code',  'like', "%{$term}%")
                   ->orWhere('quote_code', 'like', "%{$term}%")
                   ->orWhere('source',     'like', "%{$term}%");
            }))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('ExchangeRates/Trash', [
            'rates'   => $rates,
            'filters' => [
                'term'     => $term,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, ExchangeRateService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = ExchangeRate::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.exchange_rates.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'valid_at', 'direction' => 'desc']);
        }

        $rates = ExchangeRate::query()
            ->filter($request)
            ->select('exchange_rates.id', 'exchange_rates.slug', 'exchange_rates.base_code',
                     'exchange_rates.quote_code', 'exchange_rates.rate', 'exchange_rates.valid_at',
                     'exchange_rates.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('ExchangeRates/EditAll', [
            'rates'   => $rates,
            'filters' => [
                'base_code'  => $request->get('base_code', ''),
                'quote_code' => $request->get('quote_code', ''),
                'is_active'  => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'       => $request->get('sort', 'valid_at'),
                'direction'  => $request->get('direction', 'desc'),
                'per_page'   => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdateExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.exchange_rates.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, ExchangeRate $exchange_rate, ExchangeRateService $service): RedirectResponse
    {
        $clone = $service->duplicate($exchange_rate);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.exchange_rates.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.exchange_rates.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.exchange_rates.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteExchangeRateRequest $request, $slug, ExchangeRateService $service): RedirectResponse
    {
        $model = ExchangeRate::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        // Para tasas el confirm es la combinacion sintetica "USD/PEN @ YYYY-MM-DD".
        if (trim($data['display_confirmation']) !== $model->display_name) {
            return back()->withErrors(['display_confirmation' => __('exchange_rates.force_delete_display_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.exchange_rates.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(ExchangeRate $m, bool $withAudit = false): array
    {
        $base = [
            'id'           => $m->id,
            'slug'         => $m->slug,
            'base_code'    => $m->base_code,
            'quote_code'   => $m->quote_code,
            'rate'         => $m->rate,
            'valid_at'     => $m->valid_at?->format('Y-m-d H:i:s'),
            'source'       => $m->source,
            'is_active'    => $m->is_active,
            'display_name' => $m->display_name,
            'is_favorite'  => (bool) ($m->is_favorite ?? false),
            'created_at'   => $m->created_at,
            'updated_at'   => $m->updated_at,
            'deleted_at'   => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateExchangeRatesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateExchangeRatesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateExchangeRatesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateExchangeRatesWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('exchange_rates', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('exchange_rates.export_limit_exceeded', [
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
            return ExchangeRate::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return ExchangeRate::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\ExchangeRates\ExchangeRatesImportTemplate(),
            __('exchange_rates.import_template_filename')
        );
    }

    public function import(ImportExchangeRateRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\ExchangeRates\ExchangeRatesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('ExchangeRatesImport failed', [
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

    // ── BULK OPERATIONS ─────────────────────────────────────────────────
    public function bulkDelete(BulkDeleteExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            return back()
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    public function undoLastDelete(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $claim = session('exchange_rates.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('exchange_rates.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('exchange_rates.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('exchange_rates.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveExchangeRateRequest $request, ExchangeRateService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── Export helpers ──────────────────────────────────────────────────

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = [
            'id', 'base_code', 'quote_code', 'rate', 'valid_at', 'source',
            'is_active', 'slug', 'created_at', 'updated_at', 'creator',
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
            'title'                   => $data['title']                   ?? __('exchange_rates.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
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
            'auditable_type' => ExchangeRate::class,
            'auditable_id'   => null,
            'module'         => 'exchange_rates',
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
            'url'        => route('business_management.exchange_rates.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
