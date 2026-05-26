<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deliveries.edit') ?? false;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $delivery   = $this->route('delivery');
        $deliveryId = is_object($delivery) ? $delivery->id : null;

        return [
            'reference'    => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId, $deliveryId) {
                    if ($value === null || $value === '') return;
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('deliveries')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($deliveryId, fn ($qq) => $qq->where('id', '!=', $deliveryId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(reference)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(reference) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('deliveries.reference_unique'));
                    }
                },
            ],
            'sales_order_id'         => ['required', 'integer', 'exists:sales_orders,id'],
            'warehouse_id'           => ['required', 'integer', 'exists:warehouses,id'],
            'status'                 => ['required', Rule::in(Delivery::STATUSES)],
            'expected_delivery_date' => ['nullable', 'date'],
            'shipped_at'             => ['nullable', 'date'],
            'delivered_at'           => ['nullable', 'date'],
            'signed_by_name'         => ['nullable', 'string', 'max:200'],
            'carrier'                => ['nullable', 'string', 'max:100'],
            'tracking_number'        => ['nullable', 'string', 'max:80'],
            'shipping_method'        => ['nullable', 'string', 'max:60'],
            'shipping_cost'          => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:1000'],

            'items'                          => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id'    => ['required', 'integer', 'exists:sales_order_items,id'],
            'items.*.product_id'             => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('deliveries.items_required'),
            'items.min'      => __('deliveries.items_required'),
        ];
    }
}
