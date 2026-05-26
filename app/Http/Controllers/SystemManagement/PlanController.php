<?php

namespace App\Http\Controllers\SystemManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemManagement\Plan\BulkDeletePlanRequest;
use App\Http\Requests\SystemManagement\Plan\BulkRestorePlanRequest;
use App\Http\Requests\SystemManagement\Plan\BulkSetActivePlanRequest;
use App\Http\Requests\SystemManagement\Plan\DeletePlanRequest;
use App\Http\Requests\SystemManagement\Plan\EditAllUpdatePlanRequest;
use App\Http\Requests\SystemManagement\Plan\ForcePlanDeleteRequest;
use App\Http\Requests\SystemManagement\Plan\ImportPlanRequest;
use App\Http\Requests\SystemManagement\Plan\StorePlanRequest;
use App\Http\Requests\SystemManagement\Plan\UpdatePlanRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\SystemManagement\Plans\GeneratePlansCsvJob;
use App\Jobs\SystemManagement\Plans\GeneratePlansExcelJob;
use App\Jobs\SystemManagement\Plans\GeneratePlansPdfJob;
use App\Jobs\SystemManagement\Plans\GeneratePlansWordJob;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\SystemManagement\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Plans — pricing tiers editables (super only).
 *
 * Tier 1 parity con Customer master template:
 *   - CRUD + soft-delete con motivo
 *   - Bulk ops (delete/restore/set_active) con auto-async > threshold
 *   - Edit-all inline batch
 *   - Exports (CSV/Excel/PDF/Word) async + import 2-phase
 *   - Duplicate, undo last delete, force-delete con triple guard
 *   - Audit log polimorfico via Auditable trait
 *
 * Las routes ya estan gated por role:super (no permission:plans.*) —
 * super-only por diseño.
 */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', config('plans.per_page_default', 25));
        $perPage = in_array($perPage, config('plans.per_page_options', [10, 25, 50, 100])) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'sort_order', 'direction' => 'asc']);
        }

        $plans = Plan::query()
            ->select('plans.*')
            ->with('creator:id,name,email')
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Plan::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Plans/Index', [
            'plans' => array_merge($plans->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => [
                'csv'   => (int) (config('plans.export_limits.csv',   0)),
                'excel' => (int) (config('plans.export_limits.excel', 25000)),
                'pdf'   => (int) (config('plans.export_limits.pdf',   5000)),
                'word'  => (int) (config('plans.export_limits.word',  10000)),
            ],
            'filters' => [
                'name'           => array_values($names),
                'slug'           => $request->get('slug', ''),
                'support_level'  => $request->get('support_level', ''),
                'is_active'      => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'is_public'      => $request->has('is_public') && $request->is_public !== ''
                    ? filter_var($request->is_public, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'sort'           => $request->get('sort', 'sort_order'),
                'direction'      => $request->get('direction', 'asc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'supportOptions' => $this->supportOptions(),
            'filterSchema'   => Plan::filterSchema(),
            'featureKeys'    => app(PlanService::class)->featureKeys(),
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

    public function show(Request $request, Plan $plan)
    {
        $plan->load(['creator:id,name,email', 'deleter:id,name,email']);

        $tenants = $plan->tenants()
            ->select('id', 'slug', 'name', 'is_active', 'created_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'slug'       => $t->slug,
                'name'       => $t->name,
                'is_active'  => $t->is_active,
                'created_at' => $t->created_at,
            ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Plan::class)
                    ->where('auditable_id', $plan->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('Plans/Show', [
            'plan'                       => $this->planPayload($plan, withTimestamps: true, withAudit: true),
            'tenants_count'              => $plan->tenantsCount(),
            'active_subscriptions_count' => $plan->activeSubscriptionsCount(),
            'tenants'                    => $tenants,
            'activity'                   => $activity,
            'featureKeys'                => app(PlanService::class)->featureKeys(),
        ]);
    }

    public function create()
    {
        return inertia('Plans/Form', array_merge(
            ['plan' => null, 'tenants_count' => 0],
            $this->formSelectOptions(),
        ));
    }

    public function store(StorePlanRequest $request, PlanService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()
            ->route('system_management.plans.index')
            ->with('success', __('plans.created'));
    }

    public function edit(Plan $plan)
    {
        return inertia('Plans/Form', array_merge(
            ['plan' => $this->planPayload($plan), 'tenants_count' => $plan->tenantsCount()],
            $this->formSelectOptions(),
        ));
    }

    public function update(UpdatePlanRequest $request, Plan $plan, PlanService $service): RedirectResponse
    {
        $service->update($plan, $request->validated());

        return redirect()
            ->route('system_management.plans.index')
            ->with('success', __('plans.saved'));
    }

    // ── SOFT-DELETE (con motivo obligatorio) ─────────────────────────────

    public function delete(Plan $plan)
    {
        return inertia('Plans/Delete', [
            'plan'       => $this->planPayload($plan),
            'dependents' => $this->countDependents($plan),
        ]);
    }

    public function deleteSave(DeletePlanRequest $request, Plan $plan, PlanService $service): RedirectResponse
    {
        // Bloquea si hay tenants apuntando al plan (dependents `block: true`).
        $tenantsCount = $plan->tenantsCount();
        if ($tenantsCount > 0) {
            return back()->with('error', __('plans.delete_blocked', ['count' => $tenantsCount]));
        }

        $service->delete($plan, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$plan->id]);

        return redirect()
            ->route('system_management.plans.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$plan->id]));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['plans.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds((int) config('plans.undo_window_seconds', 60))->toIso8601String(),
        ]]);
    }

    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => (int) config('plans.undo_window_seconds', 60),
        ];
    }

    // ── TRASH + RESTORE + FORCE_DELETE ───────────────────────────────────

    public function trash(Request $request)
    {
        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $plans = Plan::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where(function ($qq) use ($name) {
                $qq->where('name', 'like', "%{$name}%")
                   ->orWhere('slug', 'like', "%{$name}%");
            }))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Plans/Trash', [
            'plans'   => $plans,
            'filters' => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $plan, PlanService $service): RedirectResponse
    {
        $model = Plan::onlyTrashed()->findOrFail($plan);
        $service->restore($model);

        return redirect()
            ->route('system_management.plans.trash')
            ->with('success', __('global.restored_success'));
    }

    public function forceDelete(ForcePlanDeleteRequest $request, $plan, PlanService $service): RedirectResponse
    {
        $model = Plan::onlyTrashed()->findOrFail($plan);
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('system_management.plans.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    // ── EDIT ALL ─────────────────────────────────────────────────────────

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'sort_order', 'direction' => 'asc']);
        }

        $plans = Plan::query()
            ->filter($request)
            ->select('plans.id', 'plans.slug', 'plans.name', 'plans.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Plans/EditAll', [
            'plans'   => $plans,
            'filters' => [
                'name'      => $request->get('name', ''),
                'is_active' => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'sort_order'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdatePlanRequest $request, PlanService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('system_management.plans.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    // ── DUPLICATE ────────────────────────────────────────────────────────

    public function duplicate(Request $request, Plan $plan, PlanService $service): RedirectResponse
    {
        $clone = $service->duplicate($plan);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('system_management.plans.index')
            ->with('success', __('global.duplicated_success'));
    }

    // ── BULK OPS ─────────────────────────────────────────────────────────

    public function bulkDelete(BulkDeletePlanRequest $request, PlanService $service): RedirectResponse
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

    public function bulkSetActive(BulkSetActivePlanRequest $request, PlanService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    public function bulkRestore(BulkRestorePlanRequest $request, PlanService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('system_management.plans.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('system_management.plans.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function undoLastDelete(Request $request, PlanService $service): RedirectResponse
    {
        $claim = session('plans.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('plans.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('plans.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('plans.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GeneratePlansCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GeneratePlansExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GeneratePlansPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GeneratePlansWordJob::class);
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
        $limit = (int) config('plans.export_limits.' . $format, 0);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('plans.export_limit_exceeded', [
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
            return Plan::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Plan::query()->filter($fakeReq)->count();
    }

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = [
            'id', 'slug', 'name', 'tagline', 'support_level',
            'max_users', 'max_records_per_module', 'export_rate_limit',
            'price_monthly', 'price_yearly', 'currency',
            'is_active', 'is_public', 'sort_order',
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
            'title'                   => $data['title']                   ?? __('plans.export_title'),
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
            'auditable_type' => Plan::class,
            'auditable_id'   => null,
            'module'         => 'plans',
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
            'url'        => route('system_management.plans.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ─────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SystemManagement\Plans\PlansImportTemplate(),
            __('plans.import_template_filename')
        );
    }

    public function import(ImportPlanRequest $request)
    {
        $data   = $request->validated();
        $mode   = $data['mode'] ?? 'update_or_create';
        $dryRun = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\SystemManagement\Plans\PlansImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('PlansImport failed', [
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

    // ── Helpers privados ─────────────────────────────────────────────────

    protected function formSelectOptions(): array
    {
        return [
            'featureKeys'    => app(PlanService::class)->featureKeys(),
            'supportOptions' => $this->supportOptions(),
        ];
    }

    protected function supportOptions(): array
    {
        return collect(Plan::SUPPORT_LEVELS)
            ->map(fn ($t) => ['value' => $t, 'label' => __('plans.support_' . $t)])
            ->all();
    }

    protected function planPayload(Plan $plan, bool $withTimestamps = false, bool $withAudit = false): array
    {
        $base = [
            'id'                     => $plan->id,
            'slug'                   => $plan->slug,
            'name'                   => $plan->name,
            'tagline'                => $plan->tagline,
            'icon'                   => $plan->icon,
            'color'                  => $plan->color,
            'sort_order'             => $plan->sort_order,
            'max_users'              => (int) $plan->getAttributes()['max_users'],
            'max_records_per_module' => (int) $plan->getAttributes()['max_records_per_module'],
            'export_rate_limit'      => $plan->export_rate_limit,
            'support_level'          => $plan->support_level ?: 'community',
            'features'               => $plan->features ?? [],
            'price_monthly'          => (float) $plan->price_monthly,
            'price_yearly'           => (float) $plan->price_yearly,
            'currency'               => $plan->currency,
            'is_active'              => $plan->is_active,
            'is_public'              => $plan->is_public,
            'tenants_count'          => $plan->tenantsCount(),
        ];
        if ($withTimestamps) {
            $base['created_at'] = $plan->created_at;
            $base['updated_at'] = $plan->updated_at;
            $base['deleted_at'] = $plan->deleted_at;
        }
        if ($withAudit) {
            $base['deleted_description'] = $plan->deleted_description;
            $base['creator'] = $plan->creator ? [
                'id'    => $plan->creator->id,
                'name'  => $plan->creator->name,
                'email' => $plan->creator->email,
            ] : null;
            $base['deleter'] = $plan->deleter ? [
                'id'    => $plan->deleter->id,
                'name'  => $plan->deleter->name,
                'email' => $plan->deleter->email,
            ] : null;
        }
        return $base;
    }

    /** Cuenta dependents para la pantalla Delete. */
    protected function countDependents(Plan $plan): array
    {
        $out = [];
        foreach ($plan->dependents() as $key => $config) {
            $count = ($config['count'])();
            if ($count > 0) {
                $out[$key] = [
                    'count' => $count,
                    'label' => $config['label'],
                    'block' => $config['block'] ?? false,
                ];
            }
        }
        return $out;
    }
}
