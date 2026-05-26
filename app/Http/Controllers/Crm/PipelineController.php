<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\Pipeline\BulkDeletePipelineRequest;
use App\Http\Requests\Crm\Pipeline\BulkRestorePipelineRequest;
use App\Http\Requests\Crm\Pipeline\BulkSetActivePipelineRequest;
use App\Http\Requests\Crm\Pipeline\DeletePipelineRequest;
use App\Http\Requests\Crm\Pipeline\EditAllUpdatePipelineRequest;
use App\Http\Requests\Crm\Pipeline\ForceDeletePipelineRequest;
use App\Http\Requests\Crm\Pipeline\ImportPipelineRequest;
use App\Http\Requests\Crm\Pipeline\StorePipelineRequest;
use App\Http\Requests\Crm\Pipeline\UpdatePipelineRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\Crm\Pipelines\GeneratePipelinesCsvJob;
use App\Jobs\Crm\Pipelines\GeneratePipelinesExcelJob;
use App\Jobs\Crm\Pipelines\GeneratePipelinesPdfJob;
use App\Jobs\Crm\Pipelines\GeneratePipelinesWordJob;
use App\Models\AuditLog;
use App\Models\Pipeline;
use App\Services\Crm\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
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

        // Solo super necesita el tenant eager-loaded — admins ven solo los suyos
        // y la columna workspace queda oculta en el frontend.
        $with = [
            'creator:id,name,email',
        ];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $pipelines = Pipeline::query()
            ->select('pipelines.*')
            // Counts: # stages y # deals abiertos por pipeline.
            ->withCount(['stages', 'deals as open_deals_count' => fn ($q) => $q->where('status', 'open')])
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Pipeline::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Pipelines/Index', [
            'pipelines' => array_merge($pipelines->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato — el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('pipelines'),
            'filters' => [
                'name'         => array_values($names),
'is_active'    => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from' => $request->get('created_from', ''),
                'created_to'   => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'         => $request->get('sort', 'id'),
                'direction'    => $request->get('direction', 'desc'),
                'per_page'     => $perPage,
                // Filtros avanzados: array de clausulas {field, op, value}
                // que el drawer construye. Lo persisto para que al recargar
                // la pagina (paginate, sort) el filtro siga aplicado.
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'isSuper'        => $isSuper,
            // Schema de campos filtrables — alimenta el drawer "Filtros
            // avanzados" del frontend (selects de field/op + control tipado
            // del valor). Cada modulo declara el suyo en su modelo.
            'filterSchema'   => Pipeline::filterSchema(),
        ]);
    }

    /**
     * Normaliza `advanced_where` del request: viene como JSON string o
     * array directo segun como Inertia lo serialice. Filtra clausulas
     * vacias o incompletas antes de pasarlo al frontend.
     */
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

    public function show(Request $request, Pipeline $pipeline)
    {
        $pipeline->load(['creator:id,name,email', 'deleter:id,name,email']);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;

        $stages = \App\Models\PipelineStage::where('pipeline_id', $pipeline->id)
            ->orderBy('sort_order')
            ->get(['id','slug','name','description','color','sort_order','probability_pct','is_won','is_lost','rot_days','is_active']);

        // IDs de stages — incluye soft-deleted para que el history muestre
        // eventos de etapas que el usuario haya borrado.
        $stageIds = $canSeeAudit
            ? \App\Models\PipelineStage::withTrashed()
                ->where('pipeline_id', $pipeline->id)
                ->pluck('id')->all()
            : [];

        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where(function ($q) use ($pipeline, $stageIds) {
                        $q->where(function ($q2) use ($pipeline) {
                            $q2->where('auditable_type', Pipeline::class)
                               ->where('auditable_id', $pipeline->id);
                        });
                        if (!empty($stageIds)) {
                            $q->orWhere(function ($q2) use ($stageIds) {
                                $q2->where('auditable_type', \App\Models\PipelineStage::class)
                                   ->whereIn('auditable_id', $stageIds);
                            });
                        }
                    })
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        $dealCounts = \App\Models\Deal::where('pipeline_id', $pipeline->id)
            ->where('status', 'open')
            ->selectRaw('stage_id, COUNT(*) as deal_count, SUM(value) as total_value')
            ->groupBy('stage_id')
            ->get()->keyBy('stage_id');

        $stagesWithStats = $stages->map(fn ($s) => array_merge($s->toArray(), [
            'deal_count' => (int) ($dealCounts->get($s->id)?->deal_count ?? 0),
            'total_value'=> (float) ($dealCounts->get($s->id)?->total_value ?? 0),
        ]));

        return inertia('Pipelines/Show', [
            'pipeline' => $this->payload($pipeline, withAudit: true),
            'stages'   => $stagesWithStats,
            'activity'     => $activity,
        ]);
    }

    public function create()
    {
        return inertia('Pipelines/Form', [
            'pipeline'        => null,
        ]);
    }

    public function store(StorePipelineRequest $request, PipelineService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant → no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Pipeline::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $pipeline = $service->create($request->validated());

        return redirect()
            ->route('crm.pipelines.show', $pipeline->slug)
            ->with('success', __('pipelines.created'));
    }

    public function edit(Pipeline $pipeline)
    {
        return inertia('Pipelines/Form', [
            'pipeline'        => $this->payload($pipeline),
        ]);
    }

public function update(UpdatePipelineRequest $request, Pipeline $pipeline, PipelineService $service): RedirectResponse
    {
        $service->update($pipeline, $request->validated());

        return redirect()
            ->route('crm.pipelines.show', $pipeline->slug)
            ->with('success', __('pipelines.saved'));
    }

    public function delete(Pipeline $pipeline)
    {
        // Conteo de deals abiertos para advertir al user que el borrado
        // los dejaria huerfanos (con pipeline_id apuntando a soft-deleted).
        // El frontend usa esto para bloquear el submit o mostrar warning.
        $openDealsCount = \App\Models\Deal::where('pipeline_id', $pipeline->id)
            ->where('status', 'open')
            ->count();

        return inertia('Pipelines/Delete', [
            'pipeline'        => $this->payload($pipeline),
            'openDealsCount'  => $openDealsCount,
        ]);
    }

    public function deleteSave(DeletePipelineRequest $request, Pipeline $pipeline, PipelineService $service): RedirectResponse
    {
        // Defense in depth: bloquear server-side aunque el frontend lo permita.
        // Si el pipeline tiene deals abiertos, no se puede borrar — el user
        // debe re-asignar los deals primero (o borrarlos).
        $openDealsCount = \App\Models\Deal::where('pipeline_id', $pipeline->id)
            ->where('status', 'open')
            ->count();

        if ($openDealsCount > 0) {
            return back()->withErrors([
                'pipeline' => __('pipelines.has_open_deals', ['count' => $openDealsCount]),
            ]);
        }

        $service->delete($pipeline, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$pipeline->id]);

        return redirect()
            ->route('crm.pipelines.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$pipeline->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['pipelines.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    /** Payload que va al frontend via flash para disparar el toast. */
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

        $pipelines = Pipeline::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Pipelines/Trash', [
            'pipelines' => $pipelines,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, PipelineService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Pipeline::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('crm.pipelines.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Edit All — pagina con tabla editable in-line de name + is_active.
     * El submit hace batch update en transaccion (editAllUpdate).
     */
    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $pipelines = Pipeline::query()
            ->filter($request)
            ->select('pipelines.id', 'pipelines.slug', 'pipelines.name',
                'pipelines.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Pipelines/EditAll', [
            'pipelines' => $pipelines,
            'filters'   => [
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

    public function editAllUpdate(EditAllUpdatePipelineRequest $request, PipelineService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('crm.pipelines.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    /**
     * Clona el pipeline. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, Pipeline $pipeline, PipelineService $service): RedirectResponse
    {
        $clone = $service->duplicate($pipeline);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('crm.pipelines.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestorePipelineRequest $request, PipelineService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('crm.pipelines.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('crm.pipelines.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeletePipelineRequest $request, $slug, PipelineService $service): RedirectResponse
    {
        $model = Pipeline::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('crm.pipelines.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Pipeline $m, bool $withAudit = false): array
    {
        $base = [
            'id'          => $m->id,
            'slug'        => $m->slug,
            'name'        => $m->name,
            'description' => $m->description,
            'color'       => $m->color,
            'is_default'  => (bool) $m->is_default,
            'sort_order'  => $m->sort_order,
            'is_active'   => $m->is_active,
            'is_favorite' => (bool) ($m->is_favorite ?? false),
            'created_at'  => $m->created_at,
            'updated_at'  => $m->updated_at,
            'deleted_at'  => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────
    // Los 4 formatos van a queue como jobs async (mismo patron que Regions).
    // El job se encarga de la query con scope + render + Download record.

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GeneratePipelinesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GeneratePipelinesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GeneratePipelinesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GeneratePipelinesWordJob::class);
    }

    /**
     * Helper comun de los 4 export endpoints: parse options → limit check →
     * audit → dispatch. Mismo patron que Region.
     */
    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    /**
     * Valida que el dataset no exceda el limite del formato. Usuarios con
     * plan premium (feature flag `export_unlimited_rows`) saltean el limite.
     */
    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('pipelines', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('pipelines.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    /** Cuenta filas a exportar segun scope+filters. */
    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return Pipeline::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return Pipeline::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Crm\Pipelines\PipelinesImportTemplate(),
            __('pipelines.import_template_filename')
        );
    }

    public function import(ImportPipelineRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\Crm\Pipelines\PipelinesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('PipelinesImport failed', [
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

    /**
     * Convierte una excepcion de import en mensaje legible para el usuario.
     * El detalle tecnico queda en el log, no llega al cliente.
     */
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
    public function bulkDelete(BulkDeletePipelineRequest $request, PipelineService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            // Async: el delete real ocurre despues del redirect; el undo
            // window de 60s no calza con un job que tarda minutos.
            return back()
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    /**
     * Undo dentro del window de 60s. Validamos contra session claim:
     * quien borro puede deshacer su propio error sin permisos extra.
     * Defense in depth: el service solo restaura las filas con
     * deleted_by = current user.
     */
    public function undoLastDelete(Request $request, PipelineService $service): RedirectResponse
    {
        $claim = session('pipelines.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('pipelines.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('pipelines.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('pipelines.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActivePipelineRequest $request, PipelineService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── Export helpers ──────────────────────────────────────────────────

    /**
     * Opciones normalizadas que reciben todos los jobs de export. Allowlist
     * de columnas previene inyeccion de campos sensibles.
     */
    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'name',
            'is_active', 'slug', 'created_at', 'updated_at', 'creator'];

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
            'title'                   => $data['title']                   ?? __('pipelines.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    /**
     * Escribe audit log manual del export. Event = 'export_queued' registra
     * la INTENCION del usuario; el estado final (ready/failed) vive en `downloads`.
     */
    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => Pipeline::class,
            'auditable_id'   => null,
            'module'         => 'pipelines',
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
            'url'        => route('crm.pipelines.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
