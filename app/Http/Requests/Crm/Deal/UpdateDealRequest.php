<?php

namespace App\Http\Requests\Crm\Deal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $deal   = $this->route('deal');
        $dealId = is_object($deal) ? $deal->id : null;

        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],

            'pipeline_id' => ['required', 'integer', 'exists:pipelines,id'],
            'stage_id'    => ['required', 'integer', 'exists:pipeline_stages,id'],
            'status'      => ['required', Rule::in(\App\Models\Deal::STATUSES)],

            'value'           => ['required', 'numeric', 'min:0'],
            'currency_code'   => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'probability_pct' => ['nullable', 'integer', 'min:0', 'max:100'],

            'expected_close_date' => ['nullable', 'date'],
            'won_at'              => ['nullable', 'date'],
            'lost_at'             => ['nullable', 'date'],
            'lost_reason_note'    => ['nullable', 'string', 'max:500'],

            'company_id'      => ['required', 'integer', 'exists:companies,id'],
            'contact_id'      => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id'        => ['required', 'integer', 'exists:users,id'],
            'lead_source_id'  => ['nullable', 'integer', 'exists:lead_sources,id'],

            'prefix'      => ['nullable', 'string', 'max:10'],
            'external_id' => ['nullable', 'string', 'max:100'],

            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Conditional required: cuando status=lost, hace falta un motivo
     * documentado (lost_reason_source_id o lost_reason_note). Sin esto el
     * forecast pierde insight sobre por que se cae deals — data quality.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->sometimes(
            'lost_reason_note',
            'required_without:lost_reason_source_id|string|max:500',
            fn ($input) => $input->status === 'lost',
        );
        $validator->sometimes(
            'lost_reason_source_id',
            'required_without:lost_reason_note|integer',
            fn ($input) => $input->status === 'lost',
        );
    }

    public function messages(): array
    {
        return [
            'lost_reason_note.required_without'      => __('deals.lost_reason_required'),
            'lost_reason_source_id.required_without' => __('deals.lost_reason_required'),
        ];
    }
}
