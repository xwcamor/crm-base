<?php

namespace App\Http\Requests\Crm\PipelineStage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pipelineId = $this->route('pipeline')?->id;
        $stageId    = $this->route('stage')?->id;
        $tenantId   = $this->user()?->tenant_id;

        return [
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) use ($pipelineId, $stageId, $tenantId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('pipeline_stages')
                        ->where('pipeline_id', $pipelineId)
                        ->where('tenant_id', $tenantId)
                        ->where('id', '!=', $stageId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('pipeline_stages.name_unique'));
                    }
                },
            ],
            'description'     => ['nullable', 'string', 'max:500'],
            'color'           => ['nullable', 'string', 'max:16'],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
            'probability_pct' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_won'          => ['sometimes', 'boolean'],
            'is_lost'         => ['sometimes', 'boolean'],
            'rot_days'        => ['sometimes', 'integer', 'min:0'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->boolean('is_won') && $this->boolean('is_lost')) {
                $v->errors()->add('is_won', __('pipeline_stages.won_lost_exclusive'));
            }
        });
    }
}
