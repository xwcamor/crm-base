<?php

namespace App\Http\Requests\Crm\Contact;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'       => 'required|array|min:1|max:500',
            'ids.*'     => 'integer',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'       => __('global.bulk_no_selection'),
            'is_active.required' => __('contacts.is_active_required'),
        ];
    }
}
