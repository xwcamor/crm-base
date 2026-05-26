<?php

namespace App\Http\Requests\BusinessManagement\TaxClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $taxClass   = $this->route('taxClass');
        $taxClassId = is_object($taxClass) ? $taxClass->id : null;

        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'code'        => ['nullable', 'string', 'max:30'],
            'is_default'  => ['sometimes', 'boolean'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
