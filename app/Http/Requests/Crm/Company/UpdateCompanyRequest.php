<?php

namespace App\Http\Requests\Crm\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $company   = $this->route('company');
        $companyId = is_object($company) ? $company->id : null;

        return [
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:1000'],

            // CRM fields
            'legal_name'         => ['nullable', 'string', 'max:200'],
            'tax_id'             => [
                'nullable', 'string', 'max:50',
                Rule::unique('companies', 'tax_id')
                    ->ignore($companyId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'company_type'       => ['required', Rule::in(\App\Models\Company::COMPANY_TYPES)],
            'lifecycle_stage'    => ['required', Rule::in(\App\Models\Company::LIFECYCLE_STAGES)],
            'owner_id'           => ['required', 'integer', 'exists:users,id'],
            'country_id'         => ['nullable', 'integer', 'exists:countries,id'],
            'industry_id'        => ['nullable', 'integer', 'exists:industries,id'],
            'parent_company_id'  => ['nullable', 'integer', 'exists:companies,id', "different:{$companyId}"],
            'website'            => ['nullable', 'url', 'max:255'],
            'annual_revenue'     => ['nullable', 'numeric', 'min:0'],
            'employee_count'     => ['nullable', 'integer', 'min:0'],
            'founded_year'       => ['nullable', 'integer', 'min:1800', 'max:' . (int) date('Y')],
            'external_id'        => ['nullable', 'string', 'max:100'],

            // Monetario
            'preferred_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'payment_terms_days'      => ['sometimes', 'integer', 'min:0', 'max:365'],
            'credit_limit'            => ['nullable', 'numeric', 'min:0'],

            // Comunicación
            'preferred_language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'billing_email'         => ['nullable', 'email', 'max:254'],

            // Scoring
            'rating' => ['sometimes', Rule::in(\App\Models\Company::RATINGS)],
            'score'  => ['nullable', 'integer', 'min:0', 'max:100'],

            // Compliance
            'tax_status' => ['nullable', Rule::in(\App\Models\Company::TAX_STATUSES)],

            // Social / branding
            'logo_url'       => ['nullable', 'url', 'max:500'],
            'linkedin_url'   => ['nullable', 'url', 'max:255'],
            'facebook_url'   => ['nullable', 'url', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:60'],
            'instagram_url'  => ['nullable', 'url', 'max:255'],

            'domain'              => ['nullable', 'string', 'max:120'],
            'is_vip'              => ['sometimes', 'boolean'],
            'priority'            => ['sometimes', Rule::in(\App\Models\Company::PRIORITIES)],
            'customer_since'      => ['nullable', 'date'],
            'account_manager_id'  => ['nullable', 'integer', 'exists:users,id'],

            'tax_exempt'                => ['sometimes', 'boolean'],
            'tax_exempt_reason'         => ['nullable', 'string', 'max:255'],
            'legal_entity_type'         => ['nullable', Rule::in(\App\Models\Company::LEGAL_ENTITY_TYPES)],
            'bank_account_info'         => ['nullable', 'string', 'max:2000'],
            'discount_default_pct'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'default_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'account_status'            => ['sometimes', Rule::in(\App\Models\Company::ACCOUNT_STATUSES)],
            'health_score'              => ['nullable', 'integer', 'min:0', 'max:100'],
            'churn_risk'                => ['sometimes', Rule::in(\App\Models\Company::CHURN_RISKS)],
            'referrer_company_id'       => ['nullable', 'integer', 'exists:companies,id', "different:{$companyId}"],

            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->sometimes('tax_id', 'required|string|max:50', function ($input) {
            return in_array($input->lifecycle_stage, ['customer', 'evangelist'])
                || in_array($input->company_type, ['supplier', 'both']);
        });

        $validator->sometimes('country_id', 'required|integer|exists:countries,id', function ($input) {
            return $input->lifecycle_stage === 'customer';
        });

        $validator->sometimes('preferred_currency_code', 'required|string|size:3|exists:currencies,code', function ($input) {
            return in_array($input->company_type, ['customer', 'supplier', 'both']);
        });
    }

    public function messages(): array
    {
        return [
            'company_type.required'     => __('companies.company_type_required'),
            'lifecycle_stage.required'  => __('companies.lifecycle_stage_required'),
            'owner_id.required'         => __('companies.owner_required'),
            'tax_id.required'           => __('companies.tax_id_required_conditional'),
            'country_id.required'       => __('companies.country_required_conditional'),
            'preferred_currency_code.required' => __('companies.currency_required_conditional'),
        ];
    }
}
