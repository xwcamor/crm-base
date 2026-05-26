<?php

namespace App\Http\Requests\BusinessManagement\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $product   = $this->route('product');
        $productId = is_object($product) ? $product->id : null;

        return [
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:5000'],

            'sku'         => ['nullable', 'string', 'max:60'],
            'barcode'     => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'type'        => ['required', Rule::in(\App\Models\Product::TYPES)],
            'brand'       => ['nullable', 'string', 'max:100'],

            'cost'          => ['nullable', 'numeric', 'min:0'],
            'final_cost'    => ['nullable', 'numeric', 'min:0'],
            'list_price'    => ['required', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],

            'track_inventory'     => ['sometimes', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],

            'billing_cycle'  => ['nullable', Rule::in(\App\Models\Product::BILLING_CYCLES)],
            'billing_period' => ['nullable', 'integer', 'min:1'],

            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm'  => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],

            'image_url'   => ['nullable', 'string', 'max:500'],
            'external_id' => ['nullable', 'string', 'max:100'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('billing_cycle', 'required', fn ($input) => ($input->type ?? null) === 'subscription');
    }
}
