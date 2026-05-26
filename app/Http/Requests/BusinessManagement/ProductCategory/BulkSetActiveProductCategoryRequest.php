<?php

namespace App\Http\Requests\BusinessManagement\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_categories.edit') ?? false;
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
            'is_active.required' => __('product_categories.is_active_required'),
        ];
    }
}
