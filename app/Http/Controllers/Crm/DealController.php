<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\Deal\BulkDeleteDealRequest;
use App\Http\Requests\Crm\Deal\BulkRestoreDealRequest;
use App\Http\Requests\Crm\Deal\BulkSetActiveDealRequest;
use App\Http\Requests\Crm\Deal\DeleteDealRequest;
use App\Http\Requests\Crm\Deal\EditAllUpdateDealRequest;
use App\Http\Requests\Crm\Deal\ForceDeleteDealRequest;
use App\Http\Requests\Crm\Deal\ImportDealRequest;
use App\Http\Requests\Crm\Deal\StoreDealRequest;
use App\Http\Requests\Crm\Deal\UpdateDealRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\Crm\Deals\GenerateDealsCsvJob;
use App\Jobs\Crm\Deals\GenerateDealsExcelJob;
use App\Jobs\Crm\Deals\GenerateDealsPdfJob;
use App\Jobs\Crm\Deals\GenerateDealsWordJob;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Services\Crm\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DealController extends Controller
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
            'pipeline:id,name,color',
            // company eager-cargada para Kanban (cards) y para Index (columna
            // Empresa con link). Minimal fields para no inflar JSON.
            'company:id,name,slug',
            // stage eager-cargada para mostrar el nombre + color en la
            // columna Stage del Index (mas util que solo pipeline).
            'stage:id,name,color,pipeline_id',
            // owner eager-cargado para columna Responsable.
            'owner:id,name,email',
        ];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $deals = Deal::query()
            ->select('deals.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Deal::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Deals/Index', [
            'deals' => array_merge($deals->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato — el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('deals'),
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
            'filterSchema'   => Deal::filterSchema(),
            // Pipelines + stages para la vista Kanban. El frontend agrupa
            // deals por stage_id y permite drag-and-drop entre stages del
            // MISMO pipeline. Limitado a stages de pipelines activos.
            'pipelinesWithStages' => \App\Models\Pipeline::query()
                ->where('is_active', true)
                ->with(['stages' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'color' => $p->color,
                    'stages' => $p->stages->map(fn ($s) => [
                        'id'    => $s->id,
                        'name'  => $s->name,
                        'color' => $s->color,
                        'probability_pct' => $s->probability_pct,
                    ])->values()->all(),
                ])
                ->all(),
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

    public function show(Request $request, Deal $deal)
    {
        $deal->load([
            'creator:id,name,email',
            'deleter:id,name,email',
            'pipeline:id,name,color,description',
            'pipeline.stages:id,pipeline_id,name,color,sort_order,is_won,is_lost,probability_pct,description',
            'stage:id,name,color,probability_pct,is_won,is_lost',
            'company:id,name',
            'contact:id,first_name,last_name,primary_email,primary_phone',
            'owner:id,name,email',
            'leadSource:id,name',
            'tags:id,name,color',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Deal::class)
                    ->where('auditable_id', $deal->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        $activities = $request->user()?->can('activities.view')
            ? \App\Models\Activity::query()
                ->where('activitable_type', \App\Models\Deal::class)
                ->where('activitable_id', $deal->id)
                ->with(['actor:id,name,email', 'relatedQuote:id,slug,reference,name,status,total,currency_code'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn ($a) => $a->toPanelArray())
                ->all()
            : [];

        // Quotes del deal — para que el modal de Activity pueda linkear,
        // y para mostrar la lista en una pestana/seccion del Deal Show.
        // Quote no tiene columna `name` (usa `reference`, ej. COT-2026-0001).
        // Mantenemos la clave `name` en el payload con el valor de `reference`
        // para no romper el frontend que la usa como label.
        $quotes = $request->user()?->can('quotes.view')
            ? \App\Models\Quote::query()
                ->where('deal_id', $deal->id)
                ->orderByDesc('issue_date')
                ->get(['id', 'slug', 'reference', 'status', 'grand_total', 'currency_code', 'issue_date', 'valid_until'])
                ->map(fn ($q) => [
                    'id'         => $q->id,
                    'slug'       => $q->slug,
                    'reference'  => $q->reference,
                    'name'       => $q->reference,
                    'status'     => $q->status,
                    'total'      => $q->grand_total,
                    'currency'   => $q->currency_code,
                    'issue_date' => $q->issue_date?->toDateString(),
                    'valid_until'=> $q->valid_until?->toDateString(),
                ])
                ->all()
            : [];

        return inertia('Deals/Show', [
            'deal'        => $this->payload($deal, withAudit: true),
            'activity'    => $activity,
            'activities'  => $activities,
            'quotes'      => $quotes,
            'canManageActivities' => $request->user()?->can('activities.create') ?? false,
            'canCreateQuote'      => $request->user()?->can('quotes.create') ?? false,
        ]);
    }

    /**
     * Payload de una Activity para embed en el Show del Deal. Mantiene la
     * shape consistente con ActivityController::payload pero sin parent_url
     * (no es necesario porque ya estamos viendo el parent).
     */
    protected function activityPayload(\App\Models\Activity $a): array
    {
        return [
            'id'           => $a->id,
            'slug'         => $a->slug,
            'type'         => $a->type,
            'subject'      => $a->subject,
            'body'         => $a->body,
            'due_at'       => $a->due_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'outcome'      => $a->outcome,
            'duration_min' => $a->duration_min,
            'location'     => $a->location,
            'priority'     => $a->priority,
            'is_overdue'   => $a->isOverdue(),
            'actor'        => $a->actor ? [
                'id'    => $a->actor->id,
                'name'  => $a->actor->name,
                'email' => $a->actor->email,
            ] : null,
            'created_at'   => $a->created_at?->toIso8601String(),
            'updated_at'   => $a->updated_at?->toIso8601String(),
        ];
    }

    public function create()
    {
        return inertia('Deals/Form', array_merge(
            ['deal' => null],
            $this->formSelectOptions()
        ));
    }

    protected function formSelectOptions(): array
    {
        $u = auth()->user();

        return [
            'pipelineOptions' => \App\Models\Pipeline::query()
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'is_default'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name . ($p->is_default ? ' ⭐' : '')])
                ->all(),

            // Stages se cargan TODOS, el frontend filtra por pipeline_id seleccionado.
            // probability_pct va incluida para que el form auto-actualice la
            // probabilidad del deal cuando se cambia de etapa.
            'stageOptions' => \App\Models\PipelineStage::query()
                ->where('is_active', true)->orderBy('pipeline_id')->orderBy('sort_order')
                ->get(['id', 'pipeline_id', 'name', 'is_won', 'is_lost', 'probability_pct'])
                ->map(fn ($s) => [
                    'value' => $s->id, 'label' => $s->name,
                    'pipeline_id' => $s->pipeline_id,
                    'is_won' => (bool) $s->is_won, 'is_lost' => (bool) $s->is_lost,
                    'probability_pct' => (int) $s->probability_pct,
                ])
                ->all(),

            'statusOptions' => collect(\App\Models\Deal::STATUSES)
                ->map(fn ($s) => ['value' => $s, 'label' => __('deals.status_options.' . $s)])
                ->all(),

            'companyOptions' => \App\Models\Company::query()
                ->orderBy('name')->limit(500)
                ->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
                ->all(),

            'contactOptions' => \App\Models\Contact::query()
                ->orderBy('name')->limit(500)
                ->get(['id', 'name', 'company_id'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'company_id' => $c->company_id])
                ->all(),

            'ownerOptions' => (function () use ($u) {
                $q = \App\Models\User::query();
                if (!$u || !$u->hasRole('super')) {
                    $q->when($u?->tenant_id, fn ($qq, $tid) => $qq->where('tenant_id', $tid));
                    if ($u && !$u->hasAnyRole(['super', 'admin'])) {
                        $q->where('id', $u->id);
                    }
                }
                return $q->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name . ' (' . $x->email . ')'])
                    ->all();
            })(),

            'leadSourceOptions' => \App\Models\LeadSource::query()
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])
                ->all(),

            'currencyOptions' => \App\Models\Currency::query()
                ->where('is_active', true)->orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => $c->code . ' — ' . $c->symbol . ' ' . $c->name])
                ->all(),

            'defaultCurrencyCode' => \App\Support\CurrencyResolver::forCurrentUser(),
            'defaultPipelineId'   => \App\Models\Pipeline::query()
                ->where('is_active', true)->where('is_default', true)->value('id'),
        ];
    }

    public function store(StoreDealRequest $request, DealService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant → no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Deal::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('crm.deals.index')
            ->with('success', __('deals.created'));
    }

    public function edit(Deal $deal)
    {
        return inertia('Deals/Form', array_merge(
            ['deal' => $this->payload($deal)],
            $this->formSelectOptions()
        ));
    }

public function update(UpdateDealRequest $request, Deal $deal, DealService $service): RedirectResponse
    {
        $service->update($deal, $request->validated());

        return redirect()
            ->route('crm.deals.index')
            ->with('success', __('deals.saved'));
    }

    public function delete(Deal $deal)
    {
        return inertia('Deals/Delete', [
            'deal' => $this->payload($deal),
        ]);
    }

    public function deleteSave(DeleteDealRequest $request, Deal $deal, DealService $service): RedirectResponse
    {
        $service->delete($deal, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$deal->id]);

        return redirect()
            ->route('crm.deals.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$deal->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['deals.recent_delete' => [
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

        $deals = Deal::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Deals/Trash', [
            'deals' => $deals,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, DealService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Deal::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('crm.deals.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Drag-and-drop entre stages en la vista Kanban.
     *
     * Valida que el stage_id pertenezca al mismo pipeline del deal (no se
     * permite mover un deal a un stage de OTRO pipeline — eso sera una
     * accion explicita "Cambiar pipeline" en otro PR).
     *
     * El Auditable trait registra el cambio en audit_logs automaticamente
     * via el observer del modelo, asi que no hay logging manual aqui.
     */
    public function changeStage(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $stage = \App\Models\PipelineStage::find($data['stage_id']);
        if (!$stage || $stage->pipeline_id !== $deal->pipeline_id) {
            return back()->withErrors(['stage_id' => __('deals.stage_must_match_pipeline')]);
        }

        $deal->update([
            'stage_id'        => $stage->id,
            'probability_pct' => $stage->probability_pct ?? $deal->probability_pct,
        ]);

        return back()->with('success', __('deals.stage_changed'));
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

        $deals = Deal::query()
            ->filter($request)
            ->select('deals.id', 'deals.slug', 'deals.name',
                'deals.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Deals/EditAll', [
            'deals' => $deals,
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

    public function editAllUpdate(EditAllUpdateDealRequest $request, DealService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('crm.deals.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    /**
     * Clona el deal. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, Deal $deal, DealService $service): RedirectResponse
    {
        $clone = $service->duplicate($deal);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('crm.deals.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreDealRequest $request, DealService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('crm.deals.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('crm.deals.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteDealRequest $request, $slug, DealService $service): RedirectResponse
    {
        $model = Deal::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('crm.deals.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Deal $m, bool $withAudit = false): array
    {
        // Hidrato la relacion pipeline para que el frontend pueda mostrar
        // el tag con su color sin tener que hacer lookup contra una lista.
        // Si el caller ya cargó pipeline.stages, las exponemos al frontend
        // para alimentar el DocumentFlow horizontal + checklist vertical.
        $m->loadMissing('pipeline:id,name,color');

        $pipelinePayload = null;
        if ($m->pipeline) {
            $pipelinePayload = [
                'id'    => $m->pipeline->id,
                'name'  => $m->pipeline->name,
                'color' => $m->pipeline->color,
                'description' => $m->pipeline->description ?? null,
            ];
            if ($m->pipeline->relationLoaded('stages')) {
                $pipelinePayload['stages'] = $m->pipeline->stages
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($s) => [
                        'id'              => $s->id,
                        'name'            => $s->name,
                        'color'           => $s->color,
                        'sort_order'      => $s->sort_order,
                        'is_won'          => (bool) $s->is_won,
                        'is_lost'         => (bool) $s->is_lost,
                        'probability_pct' => $s->probability_pct,
                        'description'     => $s->description ?? null,
                    ])
                    ->all();
            }
        }

        $base = [
            'id'                    => $m->id,
            'slug'                  => $m->slug,
            'tags'                  => $m->relationLoaded('tags')
                ? $m->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all()
                : [],
            'name'                  => $m->name,
            'description'           => $m->description,
            'prefix'                => $m->prefix,
            'reference'             => $m->reference,
            'pipeline_id'           => $m->pipeline_id,
            'pipeline'              => $pipelinePayload,
            'stage_id'              => $m->stage_id,
            'stage'                 => $m->relationLoaded('stage') && $m->stage ? [
                'id'              => $m->stage->id,
                'name'            => $m->stage->name,
                'color'           => $m->stage->color,
                'probability_pct' => $m->stage->probability_pct,
                'is_won'          => (bool) $m->stage->is_won,
                'is_lost'         => (bool) $m->stage->is_lost,
            ] : null,
            'status'                => $m->status,
            'value'                 => $m->value,
            'currency_code'         => $m->currency_code,
            'weighted_value'        => $m->weighted_value,
            'expected_close_date'   => $m->expected_close_date,
            'won_at'                => $m->won_at,
            'lost_at'               => $m->lost_at,
            'lost_reason_note'      => $m->lost_reason_note,
            'company_id'            => $m->company_id,
            'company'               => $m->relationLoaded('company') && $m->company ? [
                'id'   => $m->company->id,
                'name' => $m->company->name,
            ] : null,
            'contact_id'            => $m->contact_id,
            'contact'               => $m->relationLoaded('contact') && $m->contact ? [
                'id'            => $m->contact->id,
                'name'          => trim(($m->contact->first_name ?? '') . ' ' . ($m->contact->last_name ?? '')),
                'primary_email' => $m->contact->primary_email,
                'primary_phone' => $m->contact->primary_phone,
            ] : null,
            'owner_id'              => $m->owner_id,
            'owner'                 => $m->relationLoaded('owner') && $m->owner ? [
                'id'    => $m->owner->id,
                'name'  => $m->owner->name,
                'email' => $m->owner->email,
            ] : null,
            'lead_source_id'        => $m->lead_source_id,
            'lead_source'           => $m->relationLoaded('leadSource') && $m->leadSource ? [
                'id'   => $m->leadSource->id,
                'name' => $m->leadSource->name,
            ] : null,
            'probability_pct'       => $m->probability_pct,
            'external_id'           => $m->external_id,
            'is_active'             => $m->is_active,
            'is_favorite'           => (bool) ($m->is_favorite ?? false),
            'created_at'            => $m->created_at,
            'updated_at'            => $m->updated_at,
            'deleted_at'            => $m->deleted_at,
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
        return $this->dispatchExport($request, 'csv', GenerateDealsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateDealsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateDealsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateDealsWordJob::class);
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

        $limit = \App\Models\Setting::getExportLimit('deals', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('deals.export_limit_exceeded', [
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
            return Deal::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return Deal::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Crm\Deals\DealsImportTemplate(),
            __('deals.import_template_filename')
        );
    }

    public function import(ImportDealRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\Crm\Deals\DealsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('DealsImport failed', [
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
    public function bulkDelete(BulkDeleteDealRequest $request, DealService $service): RedirectResponse
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
    public function undoLastDelete(Request $request, DealService $service): RedirectResponse
    {
        $claim = session('deals.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('deals.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('deals.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('deals.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveDealRequest $request, DealService $service): RedirectResponse
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
            'title'                   => $data['title']                   ?? __('deals.export_title'),
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
            'auditable_type' => Deal::class,
            'auditable_id'   => null,
            'module'         => 'deals',
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
            'url'        => route('crm.deals.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
