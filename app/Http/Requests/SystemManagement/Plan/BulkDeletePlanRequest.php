<?php

namespace App\Http\Requests\SystemManagement\Plan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk delete masivo de planes. Defense-in-depth: el middleware role:super
 * ya gatea la ruta, este authorize() lo refuerza.
 */
class BulkDeletePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'                 => 'required|array|min:1|max:200',
            'ids.*'               => 'integer',
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'                 => __('global.bulk_no_selection'),
            'deleted_description.required' => __('global.delete_reason_required'),
            'deleted_description.min'      => __('global.delete_reason_min_3'),
        ];
    }
}
