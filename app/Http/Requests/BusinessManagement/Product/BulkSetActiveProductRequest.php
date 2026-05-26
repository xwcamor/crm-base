<?php

namespace App\Http\Requests\BusinessManagement\Product;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.edit') ?? false;
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
            'is_active.required' => __('products.is_active_required'),
        ];
    }
}
