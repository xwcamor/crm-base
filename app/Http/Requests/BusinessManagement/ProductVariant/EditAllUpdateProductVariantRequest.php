<?php

namespace App\Http\Requests\BusinessManagement\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_variants.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('product_variants.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:200',
            'changes.*.sku'       => 'sometimes|nullable|string|max:60',
            'changes.*.price'     => 'sometimes|nullable|numeric|min:0',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
