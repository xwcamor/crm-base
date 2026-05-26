<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessManagement\StockTake\BulkDeleteStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\BulkRestoreStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\BulkSetActiveStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\DeleteStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\EditAllUpdateStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\ForceDeleteStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\ImportStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\StoreStockTakeRequest;
use App\Http\Requests\BusinessManagement\StockTake\UpdateStockTakeRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\BusinessManagement\StockTakes\GenerateStockTakesCsvJob;
use App\Jobs\BusinessManagement\StockTakes\GenerateStockTakesExcelJob;
use App\Jobs\BusinessManagement\StockTakes\GenerateStockTakesPdfJob;
use App\Jobs\BusinessManagement\StockTakes\GenerateStockTakesWordJob;
use App\Models\AuditLog;
use App\Models\StockTake;
use App\Models\Warehouse;
use App\Services\BusinessManagement\StockTakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StockTakeController extends Controller
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

        $with = ['creator:id,name,email', 'warehouse:id,name,code'];
        if ($isSuper) $with[] = 'tenant:id,name';

        $takes = StockTake::query()
            ->select('stock_takes.*')
            ->with($with)
            ->withCount('lines')
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = StockTake::count();

        $refs = $request->get('reference', '');
        if (is_array($refs)) {
            $refs = array_values(array_filter($refs, fn ($r) => $r !== ''));
        }

        return inertia('StockTakes/Index', [
            'takes' => array_merge($takes->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits' => \App\Models\Setting::getExportLimits('stock_takes'),
            'filters' => [
                'reference'      => $refs,
                'status'         => $request->get('status', []),
                'warehouse_id'   => $request->get('warehouse_id', []),
                'started_from'   => $request->get('started_from', ''),
                'started_to'     => $request->get('started_to', ''),
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'warehouseOptions' => $this->warehouseOptionsLite(),
            'statusOptions'    => $this->statusOptions(),
            'isSuper'          => $isSuper,
            'filterSchema'     => StockTake::filterSchema(),
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

    public function show(Request $request, StockTake $stock_take)
    {
        $stock_take->load([
            'lines.product:id,name,sku',
            'warehouse:id,name,code',
            'creator:id,name,email',
            'completer:id,name,email',
            'deleter:id,name,email',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', StockTake::class)
                    ->where('auditable_id', $stock_take->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        return inertia('StockTakes/Show', [
            'take'     => $stock_take,
            'activity' => $activity,
        ]);
    }

    public function create()
    {
        return inertia('StockTakes/Form', array_merge(
            ['take' => null],
            $this->formOptions()
        ));
    }

    public function store(StoreStockTakeRequest $request, StockTakeService $service): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && StockTake::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $data = $request->validated();
        if (empty($data['reference'])) {
            $data['reference'] = $this->nextReference($request->user()?->tenant_id);
        }

        $take = $service->create($data);

        return redirect()
            ->route('business_management.stock_takes.show', $take->slug)
            ->with('success', __('stock_takes.created', ['count' => $take->lines()->count()]));
    }

    public function edit(StockTake $stock_take)
    {
        $stock_take->load('lines.product:id,name,sku');
        return inertia('StockTakes/Form', array_merge(['take' => $stock_take], $this->formOptions()));
    }

    public function update(UpdateStockTakeRequest $request, StockTake $stock_take, StockTakeService $service): RedirectResponse
    {
        $service->update($stock_take, $request->validated());

        return redirect()
            ->route('business_management.stock_takes.show', $stock_take->slug)
            ->with('success', __('stock_takes.saved'));
    }

    public function delete(StockTake $stock_take)
    {
        return inertia('StockTakes/Delete', [
            'take' => $this->payload($stock_take),
        ]);
    }

    public function deleteSave(DeleteStockTakeRequest $request, StockTake $stock_take, StockTakeService $service): RedirectResponse
    {
        $service->delete($stock_take, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$stock_take->id]);

        return redirect()
            ->route('business_management.stock_takes.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$stock_take->id]));
    }

    /**
     * Legacy destroy — mantiene compat con rutas DELETE sin password confirmation.
     */
    public function destroy(StockTake $stock_take, StockTakeService $service): RedirectResponse
    {
        if ($stock_take->status === 'completed') {
            return back()->with('error', __('stock_takes.cannot_delete_completed'));
        }
        $service->delete($stock_take, 'Eliminacion rapida');
        return redirect()
            ->route('business_management.stock_takes.index')
            ->with('success', __('stock_takes.deleted'));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['stock_takes.recent_delete' => [
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

        $takes = StockTake::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($reference !== '', fn ($q) => $q->where('reference', 'like', "%{$reference}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('StockTakes/Trash', [
            'takes'   => $takes,
            'filters' => [
                'reference' => $reference,
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, StockTakeService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = StockTake::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('business_management.stock_takes.trash')
            ->with('success', __('global.restored_success'));
    }

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $takes = StockTake::query()
            ->filter($request)
            ->select('stock_takes.id', 'stock_takes.slug', 'stock_takes.reference', 'stock_takes.status')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('StockTakes/EditAll', [
            'takes'   => $takes,
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

    public function editAllUpdate(EditAllUpdateStockTakeRequest $request, StockTakeService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('business_management.stock_takes.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, StockTake $stock_take, StockTakeService $service): RedirectResponse
    {
        $clone = $service->duplicate($stock_take);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('business_management.stock_takes.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreStockTakeRequest $request, StockTakeService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('business_management.stock_takes.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('business_management.stock_takes.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteStockTakeRequest $request, $slug, StockTakeService $service): RedirectResponse
    {
        $model = StockTake::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['reference_confirmation']) !== $model->reference) {
            return back()->withErrors(['reference_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('business_management.stock_takes.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(StockTake $m, bool $withAudit = false): array
    {
        $m->loadMissing(['warehouse:id,name,code']);
        $base = [
            'id'         => $m->id,
            'slug'       => $m->slug,
            'reference'  => $m->reference,
            'warehouse'  => $m->warehouse ? ['id' => $m->warehouse->id, 'name' => $m->warehouse->name, 'code' => $m->warehouse->code] : null,
            'status'     => $m->status,
            'note'       => $m->note,
            'started_at' => $m->started_at,
            'completed_at' => $m->completed_at,
            'is_favorite' => (bool) ($m->is_favorite ?? false),
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
            'deleted_at' => $m->deleted_at,
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
        return $this->dispatchExport($request, 'csv', GenerateStockTakesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateStockTakesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateStockTakesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateStockTakesWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('stock_takes', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('stock_takes.export_limit_exceeded', [
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
            return StockTake::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return StockTake::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS ──────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BusinessManagement\StockTakes\StockTakesImportTemplate(),
            __('stock_takes.import_template_filename')
        );
    }

    public function import(ImportStockTakeRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\BusinessManagement\StockTakes\StockTakesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('StockTakesImport failed', [
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

    public function bulkDelete(BulkDeleteStockTakeRequest $request, StockTakeService $service): RedirectResponse
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

    public function undoLastDelete(Request $request, StockTakeService $service): RedirectResponse
    {
        $claim = session('stock_takes.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('stock_takes.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('stock_takes.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('stock_takes.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    /**
     * Bulk update de status. La ruta se llama `bulk_set_active` por consistencia
     * con el resto de los modulos, pero aqui el target NO es un boolean sino
     * un status enum.
     */
    public function bulkSetActive(BulkSetActiveStockTakeRequest $request, StockTakeService $service): RedirectResponse
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
        $count = StockTake::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
        return sprintf('IN-%d-%04d', $year, $count);
    }

    protected function formOptions(): array
    {
        $u = auth()->user();
        return [
            'warehouseOptions'   => Warehouse::where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get(['id', 'name', 'code', 'is_default'])
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

    protected function statusOptions(): array
    {
        return collect(StockTake::STATUSES)
            ->map(fn ($s) => ['value' => $s, 'label' => __('stock_takes.status_options.' . $s)])->all();
    }

    // ── Export helpers ──────────────────────────────────────────────────

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'reference', 'warehouse', 'status', 'started_at', 'completed_at', 'note',
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
            'title'                   => $data['title']                   ?? __('stock_takes.export_title'),
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
            'auditable_type' => StockTake::class,
            'auditable_id'   => null,
            'module'         => 'stock_takes',
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
            'url'        => route('business_management.stock_takes.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
