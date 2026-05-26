<?php

namespace App\Http\Requests\BusinessManagement\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_variants.edit') ?? false;
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
            'is_active.required' => __('product_variants.is_active_required'),
        ];
    }
}
