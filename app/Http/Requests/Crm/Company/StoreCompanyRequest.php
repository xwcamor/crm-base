<?php

namespace App\Http\Requests\Crm\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
                    $q = DB::table('companies')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('companies.name_unique'));
                    }
                },
            ],
            'description'        => ['nullable', 'string', 'max:1000'],

            // CRM fields
            'legal_name'         => ['nullable', 'string', 'max:200'],
            'tax_id'             => [
                'nullable', 'string', 'max:50',
                Rule::unique('companies', 'tax_id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            // Required: type + stage + owner (data quality enterprise).
            'company_type'       => ['required', Rule::in(\App\Models\Company::COMPANY_TYPES)],
            'lifecycle_stage'    => ['required', Rule::in(\App\Models\Company::LIFECYCLE_STAGES)],
            'owner_id'           => ['required', 'integer', 'exists:users,id'],

            // Conditional required (ver withValidator).
            'country_id'         => ['nullable', 'integer', 'exists:countries,id'],
            'industry_id'        => ['nullable', 'integer', 'exists:industries,id'],
            'parent_company_id'  => ['nullable', 'integer', 'exists:companies,id'],
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

            // Priorización
            'domain'              => ['nullable', 'string', 'max:120'],
            'is_vip'              => ['sometimes', 'boolean'],
            'priority'            => ['sometimes', Rule::in(\App\Models\Company::PRIORITIES)],
            'customer_since'      => ['nullable', 'date'],
            'account_manager_id'  => ['nullable', 'integer', 'exists:users,id'],

            // Pro fields — fiscal + health
            'tax_exempt'                => ['sometimes', 'boolean'],
            'tax_exempt_reason'         => ['nullable', 'string', 'max:255'],
            'legal_entity_type'         => ['nullable', Rule::in(\App\Models\Company::LEGAL_ENTITY_TYPES)],
            'bank_account_info'         => ['nullable', 'string', 'max:2000'],
            'discount_default_pct'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'default_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'account_status'            => ['sometimes', Rule::in(\App\Models\Company::ACCOUNT_STATUSES)],
            'health_score'              => ['nullable', 'integer', 'min:0', 'max:100'],
            'churn_risk'                => ['sometimes', Rule::in(\App\Models\Company::CHURN_RISKS)],
            'referrer_company_id'       => ['nullable', 'integer', 'exists:companies,id'],

            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Conditional required rules — aplicadas según el estado de otros campos.
     * Estos triggers cubren los casos de negocio reales:
     *   - Si vas a facturar a un cliente → necesitás tax_id + country + currency.
     *   - Si vas a pagarle a un proveedor → necesitás tax_id.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->sometimes('tax_id', 'required|string|max:50', function ($input) {
            // Customer activo OR proveedor → tax_id obligatorio.
            return in_array($input->lifecycle_stage, ['customer', 'evangelist'])
                || in_array($input->company_type, ['supplier', 'both']);
        });

        $validator->sometimes('country_id', 'required|integer|exists:countries,id', function ($input) {
            // Customer activo → country obligatorio (para impuestos + shipping).
            return $input->lifecycle_stage === 'customer';
        });

        $validator->sometimes('preferred_currency_code', 'required|string|size:3|exists:currencies,code', function ($input) {
            // Customer o supplier → moneda obligatoria (define deals/quotes/invoices).
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
