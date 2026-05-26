<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_orders.edit') ?? false;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $salesOrder = $this->route('sales_order');
        $orderId    = is_object($salesOrder) ? $salesOrder->id : null;

        return [
            'reference'         => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId, $orderId) {
                    if ($value === null || $value === '') return;
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('sales_orders')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($orderId, fn ($qq) => $qq->where('id', '!=', $orderId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(reference)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(reference) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('sales_orders.reference_unique'));
                    }
                },
            ],
            'company_id'        => ['required', 'integer', 'exists:companies,id'],
            'contact_id'        => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id'          => ['nullable', 'integer', 'exists:users,id'],
            'warehouse_id'      => ['required', 'integer', 'exists:warehouses,id'],
            'status'            => ['required', Rule::in(SalesOrder::STATUSES)],
            'payment_status'    => ['required', Rule::in(SalesOrder::PAYMENT_STATUSES)],
            'order_date'        => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_code'     => ['nullable', 'string', 'size:3'],
            'payment_terms_days'=> ['nullable', 'integer', 'min:0'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'internal_notes'    => ['nullable', 'string', 'max:2000'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name'           => ['required', 'string', 'max:200'],
            'items.*.sku'            => ['nullable', 'string', 'max:60'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('sales_orders.items_required'),
            'items.min'      => __('sales_orders.items_required'),
        ];
    }
}
