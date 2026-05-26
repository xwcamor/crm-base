<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditAllUpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_orders.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('sales_orders.edit_all_max', 200);

        return [
            'changes'                  => "required|array|min:1|max:{$max}",
            'changes.*.id'             => 'required|integer',
            'changes.*.reference'      => 'sometimes|nullable|string|max:30',
            'changes.*.status'         => ['sometimes', 'nullable', Rule::in(SalesOrder::STATUSES)],
            'changes.*.payment_status' => ['sometimes', 'nullable', Rule::in(SalesOrder::PAYMENT_STATUSES)],
        ];
    }
}
