<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deliveries.create') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // Reference es unique-per-tenant (case insensitive). Identificacion
            // humana de la entrega — equivalente a `name` en Customer.
            'reference'    => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value === null || $value === '') return;
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('deliveries')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
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
            'items.required'         => __('deliveries.items_required'),
            'items.min'              => __('deliveries.items_required'),
            'sales_order_id.required' => __('deliveries.sales_order_required'),
            'warehouse_id.required'  => __('deliveries.warehouse_required'),
            'status.required'        => __('deliveries.status_required'),
        ];
    }
}
