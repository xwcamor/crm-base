<?php

namespace App\Http\Requests\SystemManagement\Plan;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActivePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'       => 'required|array|min:1|max:200',
            'ids.*'     => 'integer',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'       => __('global.bulk_no_selection'),
            'is_active.required' => __('plans.is_active_required'),
        ];
    }
}
