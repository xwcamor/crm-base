<?php

namespace App\Http\Requests\SystemManagement\Plan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacion del upload de imports de planes (super only).
 */
class ImportPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
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
