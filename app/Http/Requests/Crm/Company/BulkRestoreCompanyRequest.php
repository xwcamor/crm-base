<?php

namespace App\Http\Requests\Crm\Company;

use Illuminate\Foundation\Http\FormRequest;

class BulkRestoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // bulk_restore es operacion super: ver routes/crm.php
        // donde la ruta esta dentro de role:super. Reforzamos aca como
        // defense-in-depth.
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'   => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => __('global.bulk_no_selection'),
        ];
    }
}
