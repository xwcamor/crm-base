<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk update de status. A diferencia de Customer (que tiene `is_active`
 * boolean), SalesOrder usa `status` enum. Esta request valida que el
 * status objetivo este dentro de los valores permitidos.
 */
class BulkSetActiveSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_orders.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'status' => ['required', Rule::in(SalesOrder::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'    => __('global.bulk_no_selection'),
            'status.required' => __('sales_orders.status_required'),
        ];
    }
}
