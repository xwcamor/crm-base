<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\PriceList\BulkDeletePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\BulkRestorePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\BulkSetActivePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\DeletePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\EditAllUpdatePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\ForceDeletePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\ImportPriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\StorePriceListRequest;
use App\Http\Requests\BusinessManagement\PriceList\UpdatePriceListRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\PriceLists\GeneratePriceListsCsvJob;
use App\Jobs\BusinessManagement\PriceLists\GeneratePriceListsExcelJob;
use App\Jobs\BusinessManagement\PriceLists\GeneratePriceListsPdfJob;
use App\Jobs\BusinessManagement\PriceLists\GeneratePriceListsWordJob;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\PriceList;
use App\Services\BusinessManagement\PriceListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
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

        $with = ['creator:id,name,email'];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $priceLists = PriceList::query()
            ->select('price_lists.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = PriceList::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('PriceLists/Index', [
            'priceLists' => array_merge($priceLists->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('price_lists'),
            'filters' => [
                'name'           => array_values($names),
                'currency_code'  => $request->get('currency_code', ''),
                'is_active'      => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'is_default'     => $request->has('is_default') && $request->is_default !== ''
                    ? filter_var($request->is_default, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'currencyOptions' => $this->currencyOptions(),
            'isSuper'         => $isSuper,
            'filterSchema'    => PriceList::filterSchema(),
        ]);
    }

    protected function parseAdvancedWhere(\Illuminate\Http\Request $request): array
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

    public function show(Request $request, PriceList $price_list)
    {
        $price_list->load(['creator:id,name,email', 'deleter:id,name,email']);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', PriceList::class)
                    ->where('auditable_id', $price_list->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('PriceLists/Show', [
            'priceList' => $this->payload($price_list, withAudit: true),
            'activity'  => $activity,
        ]);
    }

    public function create()
    {
        return inertia('PriceLists/Form', array_merge(
            ['priceList' => null],
            $this->formSelectOptions()
        ));
    }

    protected function formSelectOptions(): array
    {
        return [
            'currencyOptions'     => $this->currencyOptions(),
            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
        ];
    }

    protected function currencyOptions(): array
    {
        return Currency::where('is_active', true)
            ->orderBy('code')
            ->get(['code'])
            ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code])
            ->all();
    }

    public function store(StorePriceListRequest $request, PriceListService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && PriceList::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('business_management.price_lists.index')
            ->with('success', __('price_lists.created'));
    }

    public function edit(PriceList $price_list)
    {
        return inertia('PriceLists/Form', array_merge(
            ['priceList' => $this->payload($price_list)],
            $this->formSelectOptions()
        ));
    }

    public function update(UpdatePriceListRequest $request, PriceList $price_list, PriceListService $service): RedirectResponse
    {
        $service->update($price_list, $request->validated());

        return redirect()
            ->route('business_management.price_lists.index')
            ->with('success', __('price_lists.saved'));
    }

    public function delete(PriceList $price_list)
    {
        return inertia('PriceLists/Delete', [
            'priceList' => $this->payload($price_list),
        ]);
    }

    public function deleteSave(DeletePriceListRequest $request, PriceList $price_list, PriceListService $service): RedirectResponse
    {
        $service->delete($price_list, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$price_list->id]);

        return redirect()
            ->route('business_management.price_lists.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$price_list->id]));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['price_lists.recent_delete' => [
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

        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $priceLists = PriceList::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('PriceLists/Trash', [
            'priceLists' => $priceLists,
            'filters'    => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, PriceListService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = PriceList::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.price_lists.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $priceLists = PriceList::query()
            ->filter($request)
            ->select('price_lists.id', 'price_lists.slug', 'price_lists.name', 'price_lists.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('PriceLists/EditAll', [
            'priceLists' => $priceLists,
            'filters'    => [
                'name'      => $request->get('name', ''),
                'is_active' => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdatePriceListRequest $request, PriceListService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.price_lists.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, PriceList $price_list, PriceListService $service): RedirectResponse
    {
        $clone = $service->duplicate($price_list);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.price_lists.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestorePriceListRequest $request, PriceListService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.price_lists.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.price_lists.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeletePriceListRequest $request, $slug, PriceListService $service): RedirectResponse
    {
        $model = PriceList::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.price_lists.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(PriceList $m, bool $withAudit = false): array
    {
        $base = [
            'id'                  => $m->id,
            'slug'                => $m->slug,
            'name'                => $m->name,
            'description'         => $m->description,
            'currency_code'       => $m->currency_code,
            'global_discount_pct' => $m->global_discount_pct,
            'priority'            => $m->priority,
            'valid_from'          => $m->valid_from?->format('Y-m-d H:i:s'),
            'valid_until'         => $m->valid_until?->format('Y-m-d H:i:s'),
            'is_default'          => $m->is_default,
            'is_active'           => $m->is_active,
            'is_favorite'         => (bool) ($m->is_favorite ?? false),
            'created_at'          => $m->created_at,
            'updated_at'          => $m->updated_at,
            'deleted_at'          => $m->deleted_at,
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
        return $this->dispatchExport($request, 'csv', GeneratePriceListsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GeneratePriceListsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GeneratePriceListsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GeneratePriceListsWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('price_lists', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('price_lists.export_limit_exceeded', [
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
            return PriceList::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return PriceList::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\PriceLists\PriceListsImportTemplate(),
            __('price_lists.import_template_filename')
        );
    }

    public function import(ImportPriceListRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\PriceLists\PriceListsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('PriceListsImport failed', [
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
    public function bulkDelete(BulkDeletePriceListRequest $request, PriceListService $service): RedirectResponse
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

    public function undoLastDelete(Request $request, PriceListService $service): RedirectResponse
    {
        $claim = session('price_lists.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('price_lists.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('price_lists.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('price_lists.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActivePriceListRequest $request, PriceListService $service): RedirectResponse
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
            'id', 'name', 'description', 'currency_code', 'global_discount_pct', 'priority',
            'valid_from', 'valid_until', 'is_default', 'is_active',
            'slug', 'created_at', 'updated_at', 'creator',
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
            'title'                   => $data['title']                   ?? __('price_lists.export_title'),
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
            'auditable_type' => PriceList::class,
            'auditable_id'   => null,
            'module'         => 'price_lists',
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
            'url'        => route('business_management.price_lists.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
