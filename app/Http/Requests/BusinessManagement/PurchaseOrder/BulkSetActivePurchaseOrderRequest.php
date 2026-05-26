<?php

namespace App\Http\Requests\BusinessManagement\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk set-status para PurchaseOrders (clon de BulkSetActiveCustomerRequest).
 *
 * PurchaseOrder no tiene `is_active` boolean (Customer si). El equivalente
 * semantico aca es transicionar el `status` (draft/submitted/confirmed/etc).
 * Conservamos el nombre del FormRequest para mantener simetria con el master.
 */
class BulkSetActivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase_orders.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'    => __('global.bulk_no_selection'),
            'status.required' => __('purchase_orders.status_required'),
        ];
    }
}
