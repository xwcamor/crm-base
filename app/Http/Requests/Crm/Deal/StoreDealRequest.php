<?php

namespace App\Http\Requests\Crm\Deal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // Unicidad case + accent insensitive dentro del workspace.
            // "Acme S.A." y "ACME S.A." se consideran duplicados — mismo
            // patron que Regions/Languages/etc. El constraint UNIQUE de la
            // BD es el ultimo guardrail; esta validacion lo detecta antes.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('deals')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('deals.name_unique'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],

            // Pipeline + stage (REQUIRED — sin esto el deal no se puede ubicar en el kanban)
            'pipeline_id' => ['required', 'integer', 'exists:pipelines,id'],
            'stage_id'    => ['required', 'integer', 'exists:pipeline_stages,id'],
            'status'      => ['required', \Illuminate\Validation\Rule::in(\App\Models\Deal::STATUSES)],

            // Monetario
            'value'           => ['required', 'numeric', 'min:0'],
            'currency_code'   => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'probability_pct' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Fechas
            'expected_close_date' => ['nullable', 'date'],
            'won_at'              => ['nullable', 'date'],
            'lost_at'             => ['nullable', 'date'],
            'lost_reason_note'    => ['nullable', 'string', 'max:500'],

            // Relación
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
