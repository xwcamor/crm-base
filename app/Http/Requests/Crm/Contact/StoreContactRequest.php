<?php

namespace App\Http\Requests\Crm\Contact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // En Contacts NO chequeamos unicidad por name (dos "Juan Pérez"
            // pueden coexistir en el mismo workspace si son de diferentes
            // companies). La unicidad va por primary_email.
            // `name` se autoarma desde first+last si va vacío.
            'name'         => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:1000'],

            // Identidad — al menos UNO de first_name/last_name es obligatorio.
            'first_name'   => ['required_without:last_name', 'nullable', 'string', 'max:120'],
            'last_name'    => ['required_without:first_name', 'nullable', 'string', 'max:120'],
            'middle_name'  => ['nullable', 'string', 'max:120'],
            'salutation'   => ['nullable', Rule::in(\App\Models\Contact::SALUTATIONS)],
            'job_title'    => ['nullable', 'string', 'max:150'],
            'department'   => ['nullable', 'string', 'max:120'],

            // Al menos UNO de email/phone (un contacto sin contacto no sirve).
            'primary_email' => [
                'required_without_all:primary_phone,mobile_phone',
                'nullable', 'email', 'max:254',
                Rule::unique('contacts', 'primary_email')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'primary_phone' => ['required_without_all:primary_email,mobile_phone', 'nullable', 'string', 'max:30'],
            'mobile_phone'  => ['nullable', 'string', 'max:30'],

            // Relación CRM
            'company_id'             => ['nullable', 'integer', 'exists:companies,id'],
            'reports_to_contact_id'  => ['nullable', 'integer', 'exists:contacts,id'],
            'is_primary_for_company' => ['sometimes', 'boolean'],

            // Clasificación — lifecycle obligatorio (tiene default 'lead').
            'lifecycle_stage' => ['required', Rule::in(\App\Models\Contact::LIFECYCLE_STAGES)],
            'lead_source'     => ['nullable', 'string', 'max:60'],
            'rating'          => ['sometimes', Rule::in(\App\Models\Contact::RATINGS)],
            'score'           => ['nullable', 'integer', 'min:0', 'max:100'],

            // Asignación — owner obligatorio (accountability).
            'owner_id'              => ['required', 'integer', 'exists:users,id'],
            'preferred_language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'timezone'              => ['nullable', 'string', 'max:60'],

            // Compliance
            'email_opt_in'    => ['sometimes', 'boolean'],
            'sms_opt_in'      => ['sometimes', 'boolean'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'do_not_contact'  => ['sometimes', 'boolean'],

            // Personal
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender'        => ['nullable', Rule::in(\App\Models\Contact::GENDERS)],

            // Social
            'linkedin_url'   => ['nullable', 'url', 'max:255'],
            'twitter_handle' => ['nullable', 'string', 'max:60'],
            'photo_url'      => ['nullable', 'url', 'max:500'],
            'external_id'    => ['nullable', 'string', 'max:100'],

            // Sales qualification
            'nickname'          => ['nullable', 'string', 'max:60'],
            'seniority_level'   => ['nullable', Rule::in(\App\Models\Contact::SENIORITY_LEVELS)],
            'decision_role'     => ['nullable', Rule::in(\App\Models\Contact::DECISION_ROLES)],
            'is_decision_maker' => ['sometimes', 'boolean'],
            'preferred_channel' => ['nullable', Rule::in(\App\Models\Contact::PREFERRED_CHANNELS)],

            // Assistant
            'assistant_name'  => ['nullable', 'string', 'max:200'],
            'assistant_email' => ['nullable', 'email', 'max:254'],
            'assistant_phone' => ['nullable', 'string', 'max:30'],

            // Marketing compliance
            'marketing_opt_in_at'      => ['nullable', 'date'],
            'marketing_opt_in_source'  => ['nullable', 'string', 'max:120'],
            'unsubscribed_at'          => ['nullable', 'date'],
            'unsubscribed_reason'      => ['nullable', 'string', 'max:255'],
            'relationship_strength'    => ['sometimes', Rule::in(\App\Models\Contact::RELATIONSHIP_STRENGTHS)],

            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required_without' => __('contacts.first_or_last_required'),
            'last_name.required_without'  => __('contacts.first_or_last_required'),
            'primary_email.required_without_all' => __('contacts.email_or_phone_required'),
            'primary_phone.required_without_all' => __('contacts.email_or_phone_required'),
            'lifecycle_stage.required' => __('contacts.lifecycle_stage_required'),
            'owner_id.required' => __('contacts.owner_required'),
        ];
    }
}
