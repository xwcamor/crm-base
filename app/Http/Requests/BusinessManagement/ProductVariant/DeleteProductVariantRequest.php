<?php

namespace App\Http\Requests\BusinessManagement\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }
}
