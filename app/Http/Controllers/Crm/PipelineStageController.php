<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\PipelineStage\StorePipelineStageRequest;
use App\Http\Requests\Crm\PipelineStage\UpdatePipelineStageRequest;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sub-recurso del Pipeline: kanban-stages CRUD + reorder.
 *
 * Stages son hijos de Pipeline (1:N). NO son un modulo top-level — viven
 * dentro del Show del pipeline (drawer) y usan el permiso pipelines.edit.
 *
 * Deletion guard: no se permite borrar una stage que tiene deals abiertos.
 * El user debe mover los deals primero (o borrarlos) — patron Pipedrive.
 */
class PipelineStageController extends Controller
{
    public function store(StorePipelineStageRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $maxOrder = PipelineStage::where('pipeline_id', $pipeline->id)->max('sort_order') ?? 0;

        PipelineStage::create([
            'pipeline_id'     => $pipeline->id,
            'tenant_id'       => $request->user()?->tenant_id,
            'created_by'      => $request->user()?->id,
            'name'            => $request->input('name'),
            'description'     => $request->input('description'),
            'color'           => $request->input('color', '#888888'),
            'sort_order'      => (int) $request->input('sort_order', $maxOrder + 1),
            'probability_pct' => (int) $request->input('probability_pct', 0),
            'is_won'          => $request->boolean('is_won'),
            'is_lost'         => $request->boolean('is_lost'),
            'rot_days'        => (int) $request->input('rot_days', 0),
            'is_active'       => $request->boolean('is_active', true),
        ]);

        return back()->with('success', __('pipeline_stages.created'));
    }

    public function update(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->update([
            'name'            => $request->input('name'),
            'description'     => $request->input('description'),
            'color'           => $request->input('color', $stage->color),
            'sort_order'      => $request->has('sort_order')      ? (int) $request->input('sort_order')      : $stage->sort_order,
            'probability_pct' => $request->has('probability_pct') ? (int) $request->input('probability_pct') : $stage->probability_pct,
            'is_won'          => $request->boolean('is_won'),
            'is_lost'         => $request->boolean('is_lost'),
            'rot_days'        => $request->has('rot_days') ? (int) $request->input('rot_days') : $stage->rot_days,
            'is_active'       => $request->boolean('is_active', $stage->is_active),
        ]);

        return back()->with('success', __('pipeline_stages.saved'));
    }

    public function destroy(Request $request, Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $dealsCount = Deal::where('stage_id', $stage->id)->count();
        if ($dealsCount > 0) {
            return back()->withErrors([
                'stage' => __('pipeline_stages.has_deals', ['count' => $dealsCount]),
            ]);
        }

        $stage->update(['deleted_by' => $request->user()?->id]);
        $stage->delete();

        return back()->with('success', __('pipeline_stages.deleted'));
    }

    public function reorder(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($validated['order'] as $position => $stageId) {
            PipelineStage::where('id', $stageId)
                ->where('pipeline_id', $pipeline->id)
                ->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', __('pipeline_stages.reordered'));
    }
}
