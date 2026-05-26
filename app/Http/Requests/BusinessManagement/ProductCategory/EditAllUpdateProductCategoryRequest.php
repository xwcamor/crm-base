<?php

namespace App\Http\Requests\BusinessManagement\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_categories.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('product_categories.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:150',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
