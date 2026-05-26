<?php

namespace App\Http\Requests\BusinessManagement\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $variant   = $this->route('product_variant');
        $variantId = is_object($variant) ? $variant->id : null;

        return [
            'name' => ['required', 'string', 'max:200'],
            'sku'  => [
                'required', 'string', 'max:60',
                function ($attribute, $value, $fail) use ($tenantId, $variantId) {
                    $needle = trim((string) $value);
                    $exists = DB::table('product_variants')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($variantId, fn ($qq) => $qq->where('id', '!=', $variantId))
                        ->whereRaw('LOWER(sku) = LOWER(?)', [$needle])
                        ->exists();
                    if ($exists) {
                        $fail(__('product_variants.sku_unique'));
                    }
                },
            ],
            'product_id'          => ['required', 'integer', 'exists:products,id'],
            'barcode'             => ['nullable', 'string', 'max:60'],
            'attributes'          => ['nullable', 'array'],
            'cost'                => ['nullable', 'numeric', 'min:0'],
            'price'               => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url'           => ['nullable', 'string', 'max:500'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }
}
