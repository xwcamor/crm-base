<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\Activity\StoreActivityRequest;
use App\Http\Requests\Crm\Activity\UpdateActivityRequest;
use App\Models\Activity;
use App\Services\Crm\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Index global de actividades — vista en /crm/activities con filtros.
     * Cada user ve solo las de su tenant (BelongsToTenant scope).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        $userId = $request->user()?->id;

        $query = Activity::query()
            ->with(['actor:id,name,email', 'activitable', 'relatedQuote:id,slug,reference,name']);

        // Filtros basicos
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->filled('status')) {
            switch ($request->get('status')) {
                case 'pending':   $query->whereNull('completed_at'); break;
                case 'completed': $query->whereNotNull('completed_at'); break;
                case 'overdue':   $query->whereNull('completed_at')
                                        ->whereNotNull('due_at')
                                        ->where('due_at', '<', now()); break;
            }
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->get('priority'));
        }
        if ($request->filled('scope') && $request->get('scope') === 'mine') {
            $query->where('actor_user_id', $userId);
        }
        if ($request->filled('q')) {
            $q = '%' . $request->get('q') . '%';
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', $q)->orWhere('body', 'like', $q);
            });
        }

        // Filtros por pipeline/stage del Deal padre — solo aplican a activities
        // cuyo activitable es Deal. Hacemos subquery contra la tabla deals.
        $pipelineId = $request->get('pipeline_id');
        $stageId    = $request->get('stage_id');
        $dealStatus = $request->get('deal_status');
        if ($pipelineId || $stageId || $dealStatus) {
            $query->where('activitable_type', \App\Models\Deal::class);
            $query->whereExists(function ($sq) use ($pipelineId, $stageId, $dealStatus) {
                $sq->select(\DB::raw(1))
                    ->from('deals')
                    ->whereColumn('deals.id', 'activities.activitable_id');
                if ($pipelineId) $sq->where('deals.pipeline_id', $pipelineId);
                if ($stageId)    $sq->where('deals.stage_id', $stageId);
                if ($dealStatus) $sq->where('deals.status', $dealStatus);
            });
        }

        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $allowedSorts = ['created_at', 'due_at', 'completed_at', 'type'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
        if (!in_array($direction, ['asc', 'desc'])) $direction = 'desc';
        $query->orderBy($sort, $direction);

        $activities = $query->paginate($perPage)->withQueryString();

        // Options para los filtros del frontend
        $pipelineOptions = \App\Models\Pipeline::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'color' => $p->color])
            ->all();

        $stageOptions = \App\Models\PipelineStage::query()
            ->where('is_active', true)
            ->orderBy('pipeline_id')
            ->orderBy('sort_order')
            ->get(['id', 'pipeline_id', 'name'])
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'pipeline_id' => $s->pipeline_id])
            ->all();

        return inertia('Activities/Index', [
            'activities' => array_merge($activities->toArray(), [
                'data' => collect($activities->items())->map(fn ($a) => $this->payload($a))->all(),
            ]),
            'filters' => [
                'type'        => $request->get('type', ''),
                'status'      => $request->get('status', ''),
                'priority'    => $request->get('priority', ''),
                'scope'       => $request->get('scope', ''),
                'pipeline_id' => $request->get('pipeline_id', null),
                'stage_id'    => $request->get('stage_id', null),
                'deal_status' => $request->get('deal_status', ''),
                'q'           => $request->get('q', ''),
                'sort'        => $sort,
                'direction'   => $direction,
                'per_page'    => $perPage,
            ],
            'meta' => [
                'types'           => Activity::TYPES,
                'priorities'      => Activity::PRIORITIES,
                'outcomes'        => Activity::CALL_OUTCOMES,
                'pipelineOptions' => $pipelineOptions,
                'stageOptions'    => $stageOptions,
                'dealStatuses'    => \App\Models\Deal::STATUSES,
            ],
        ]);
    }

    public function store(StoreActivityRequest $request, ActivityService $service): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        $service->create($data, $request->file('attachment'));

        return back()->with('success', __('activities.created'));
    }

    public function update(UpdateActivityRequest $request, Activity $activity, ActivityService $service): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        $service->update($activity, $data, $request->file('attachment'));

        return back()->with('success', __('activities.saved'));
    }

    public function destroy(Activity $activity, ActivityService $service): RedirectResponse
    {
        $service->delete($activity);

        return back()->with('success', __('activities.deleted'));
    }

    public function markComplete(Activity $activity, ActivityService $service): RedirectResponse
    {
        $service->markComplete($activity);

        return back()->with('success', __('activities.completed'));
    }

    public function markPending(Activity $activity, ActivityService $service): RedirectResponse
    {
        $service->markPending($activity);

        return back()->with('success', __('activities.reopened'));
    }

    /**
     * Payload de una activity para frontend. Incluye actor name + un breve
     * resumen del parent (nombre legible) para que el global index pueda
     * linkear sin lookups extras.
     */
    protected function payload(Activity $a): array
    {
        $parentLabel = null;
        $parentUrl   = null;
        $parentExtra = null;
        if ($a->activitable) {
            $parentLabel = match (true) {
                $a->activitable_type === \App\Models\Deal::class    => $a->activitable->name,
                $a->activitable_type === \App\Models\Company::class => $a->activitable->name,
                $a->activitable_type === \App\Models\Contact::class => trim(($a->activitable->first_name ?? '') . ' ' . ($a->activitable->last_name ?? '')),
                default => null,
            };
            try {
                $parentUrl = match ($a->activitable_type) {
                    \App\Models\Deal::class    => route('crm.deals.show',     $a->activitable->slug),
                    \App\Models\Company::class => route('crm.companies.show', $a->activitable->slug),
                    \App\Models\Contact::class => route('crm.contacts.show',  $a->activitable->slug),
                    default => null,
                };
            } catch (\Throwable $e) {
                $parentUrl = null;
            }

            // Si el parent es un Deal, incluyo pipeline/stage/status para que la
            // tabla pueda mostrar columnas y badges.
            if ($a->activitable_type === \App\Models\Deal::class) {
                $a->activitable->loadMissing(['pipeline:id,name,color', 'stage:id,name,color']);
                $parentExtra = [
                    'deal_status' => $a->activitable->status,
                    'deal_value'  => $a->activitable->value,
                    'deal_currency' => $a->activitable->currency_code,
                    'pipeline'    => $a->activitable->pipeline ? [
                        'id' => $a->activitable->pipeline->id,
                        'name' => $a->activitable->pipeline->name,
                        'color' => $a->activitable->pipeline->color,
                    ] : null,
                    'stage'       => $a->activitable->stage ? [
                        'id' => $a->activitable->stage->id,
                        'name' => $a->activitable->stage->name,
                        'color' => $a->activitable->stage->color,
                    ] : null,
                ];
            }
        }

        // Related quote info
        $quote = null;
        if ($a->related_quote_id && $a->relatedQuote) {
            try {
                $quoteUrl = route('business_management.quotes.show', $a->relatedQuote->slug);
            } catch (\Throwable $e) {
                $quoteUrl = null;
            }
            $quote = [
                'id'        => $a->relatedQuote->id,
                'slug'      => $a->relatedQuote->slug,
                'reference' => $a->relatedQuote->reference,
                'name'      => $a->relatedQuote->name,
                'url'       => $quoteUrl,
            ];
        }

        return [
            'id'              => $a->id,
            'slug'            => $a->slug,
            'type'            => $a->type,
            'subject'         => $a->subject,
            'body'            => $a->body,
            'due_at'          => $a->due_at?->toIso8601String(),
            'completed_at'    => $a->completed_at?->toIso8601String(),
            'outcome'         => $a->outcome,
            'duration_min'   => $a->duration_min,
            'location'        => $a->location,
            'priority'        => $a->priority,
            'attachment_path' => $a->attachment_path,
            'attachment_name' => $a->attachment_name,
            'is_overdue'      => $a->isOverdue(),
            'activitable_type'=> class_basename($a->activitable_type),
            'activitable_id'  => $a->activitable_id,
            'parent_label'    => $parentLabel,
            'parent_url'      => $parentUrl,
            'parent_extra'    => $parentExtra,
            'related_quote'   => $quote,
            'actor'           => $a->actor ? [
                'id'    => $a->actor->id,
                'name'  => $a->actor->name,
                'email' => $a->actor->email,
            ] : null,
            'created_at'      => $a->created_at?->toIso8601String(),
            'updated_at'      => $a->updated_at?->toIso8601String(),
        ];
    }
}
