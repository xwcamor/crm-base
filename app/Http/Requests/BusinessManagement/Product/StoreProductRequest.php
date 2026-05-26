<?php

namespace App\Http\Requests\BusinessManagement\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // Unicidad case + accent insensitive dentro del workspace.
            // "Acme S.A." y "ACME S.A." se consideran duplicados — mismo
            // patron que Regions/Languages/etc. El constraint UNIQUE de la
            // BD es el ultimo guardrail; esta validacion lo detecta antes.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('products')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('products.name_unique'));
                    }
                },
            ],
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

    public function messages(): array
    {
        return [
            'name.required'       => __('products.name_required'),
            'list_price.required' => __('products.list_price_required'),
            'type.required'       => __('products.type_required'),
            'billing_cycle.required' => __('products.billing_cycle_required'),
        ];
    }
}
