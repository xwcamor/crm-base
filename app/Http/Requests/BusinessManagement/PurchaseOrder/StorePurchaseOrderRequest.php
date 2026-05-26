<?php

namespace App\Http\Requests\BusinessManagement\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'reference' => [
                'nullable', 'string', 'max:30',
                // Unicidad case-insensitive de `reference` por tenant. Si esta
                // en blanco, el controller genera el siguiente correlativo.
                function ($attribute, $value, $fail) use ($tenantId) {
                    $value = trim((string) $value);
                    if ($value === '') return;
                    $exists = DB::table('purchase_orders')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->whereRaw('LOWER(reference) = LOWER(?)', [$value])
                        ->exists();
                    if ($exists) {
                        $fail(__('purchase_orders.reference_unique'));
                    }
                },
            ],
            'supplier_company_id'    => ['required', 'integer', 'exists:companies,id'],
            'owner_id'               => ['nullable', 'integer', 'exists:users,id'],
            'warehouse_id'           => ['required', 'integer', 'exists:warehouses,id'],
            'status'                 => ['required', Rule::in(PurchaseOrder::STATUSES)],
            'order_date'             => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'payment_terms_days'     => ['nullable', 'integer', 'min:0'],
            'delivery_type'          => ['nullable', 'string', 'max:30'],
            'notes'                  => ['nullable', 'string', 'max:2000'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name'           => ['required', 'string', 'max:200'],
            'items.*.description'    => ['nullable', 'string', 'max:1000'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('purchase_orders.items_required'),
            'items.min'      => __('purchase_orders.items_required'),
        ];
    }
}
