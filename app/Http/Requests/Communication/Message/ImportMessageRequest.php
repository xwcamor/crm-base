<?php

namespace App\Http\Requests\Communication\Message;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacion del upload de imports. Clon del ImportRequest de Discounts.
 *
 * Messages NO acepta HTML rico via import (el rich body se compone en la UI).
 * Aqui solo se importan filas plain con subject/body/audience_type/etc.
 */
class ImportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super');
    }

    public function rules(): array
    {
        return [
            'file'    => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'mode'    => 'nullable|in:create_only,update_or_create',
            'dry_run' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('imports.file_required'),
            'file.mimes'    => __('imports.file_mimes'),
            'file.max'      => __('imports.file_max'),
        ];
    }
}
