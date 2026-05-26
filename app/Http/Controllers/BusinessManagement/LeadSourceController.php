<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\LeadSource\BulkDeleteLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\BulkRestoreLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\BulkSetActiveLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\DeleteLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\EditAllUpdateLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\ForceDeleteLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\ImportLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\StoreLeadSourceRequest;
use App\Http\Requests\BusinessManagement\LeadSource\UpdateLeadSourceRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\LeadSources\GenerateLeadSourcesCsvJob;
use App\Jobs\BusinessManagement\LeadSources\GenerateLeadSourcesExcelJob;
use App\Jobs\BusinessManagement\LeadSources\GenerateLeadSourcesPdfJob;
use App\Jobs\BusinessManagement\LeadSources\GenerateLeadSourcesWordJob;
use App\Models\AuditLog;
use App\Models\LeadSource;
use App\Services\BusinessManagement\LeadSourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadSourceController extends Controller
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

        $leadSources = LeadSource::query()
            ->select('lead_sources.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = LeadSource::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('LeadSources/Index', [
            'leadSources' => array_merge($leadSources->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('lead_sources'),
            'filters' => [
                'name'           => array_values($names),
                'category'       => $request->get('category', ''),
                'is_active'      => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'isSuper'      => $isSuper,
            'filterSchema' => LeadSource::filterSchema(),
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

    public function show(Request $request, LeadSource $lead_source)
    {
        $lead_source->load([
            'creator:id,name,email',
            'deleter:id,name,email',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', LeadSource::class)
                    ->where('auditable_id', $lead_source->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('LeadSources/Show', [
            'leadSource' => $this->payload($lead_source, withAudit: true),
            'activity'   => $activity,
        ]);
    }

    public function create()
    {
        return inertia('LeadSources/Form', [
            'leadSource' => null,
        ]);
    }

    public function store(StoreLeadSourceRequest $request, LeadSourceService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && LeadSource::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('business_management.lead_sources.index')
            ->with('success', __('lead_sources.created'));
    }

    public function edit(LeadSource $lead_source)
    {
        return inertia('LeadSources/Form', [
            'leadSource' => $this->payload($lead_source),
        ]);
    }

    public function update(UpdateLeadSourceRequest $request, LeadSource $lead_source, LeadSourceService $service): RedirectResponse
    {
        $service->update($lead_source, $request->validated());

        return redirect()
            ->route('business_management.lead_sources.index')
            ->with('success', __('lead_sources.saved'));
    }

    public function delete(LeadSource $lead_source)
    {
        return inertia('LeadSources/Delete', [
            'leadSource' => $this->payload($lead_source),
        ]);
    }

    public function deleteSave(DeleteLeadSourceRequest $request, LeadSource $lead_source, LeadSourceService $service): RedirectResponse
    {
        $service->delete($lead_source, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$lead_source->id]);

        return redirect()
            ->route('business_management.lead_sources.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$lead_source->id]));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['lead_sources.recent_delete' => [
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

        $leadSources = LeadSource::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('LeadSources/Trash', [
            'leadSources' => $leadSources,
            'filters'     => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, LeadSourceService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = LeadSource::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.lead_sources.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $leadSources = LeadSource::query()
            ->filter($request)
            ->select('lead_sources.id', 'lead_sources.slug', 'lead_sources.name', 'lead_sources.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('LeadSources/EditAll', [
            'leadSources' => $leadSources,
            'filters'     => [
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

    public function editAllUpdate(EditAllUpdateLeadSourceRequest $request, LeadSourceService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.lead_sources.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, LeadSource $lead_source, LeadSourceService $service): RedirectResponse
    {
        $clone = $service->duplicate($lead_source);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.lead_sources.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreLeadSourceRequest $request, LeadSourceService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.lead_sources.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.lead_sources.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteLeadSourceRequest $request, $slug, LeadSourceService $service): RedirectResponse
    {
        $model = LeadSource::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.lead_sources.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(LeadSource $m, bool $withAudit = false): array
    {
        $base = [
            'id'           => $m->id,
            'slug'         => $m->slug,
            'name'         => $m->name,
            'description'  => $m->description,
            'category'     => $m->category,
            'sort_order'   => $m->sort_order,
            'is_active'    => $m->is_active,
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
        return $this->dispatchExport($request, 'csv', GenerateLeadSourcesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateLeadSourcesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateLeadSourcesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateLeadSourcesWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('lead_sources', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('lead_sources.export_limit_exceeded', [
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
            return LeadSource::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return LeadSource::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\LeadSources\LeadSourcesImportTemplate(),
            __('lead_sources.import_template_filename')
        );
    }

    public function import(ImportLeadSourceRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\LeadSources\LeadSourcesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('LeadSourcesImport failed', [
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
    public function bulkDelete(BulkDeleteLeadSourceRequest $request, LeadSourceService $service): RedirectResponse
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

    public function undoLastDelete(Request $request, LeadSourceService $service): RedirectResponse
    {
        $claim = session('lead_sources.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('lead_sources.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('lead_sources.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('lead_sources.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveLeadSourceRequest $request, LeadSourceService $service): RedirectResponse
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
            'id', 'name', 'description', 'category', 'sort_order',
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
            'title'                   => $data['title']                   ?? __('lead_sources.export_title'),
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
            'auditable_type' => LeadSource::class,
            'auditable_id'   => null,
            'module'         => 'lead_sources',
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
            'url'        => route('business_management.lead_sources.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
