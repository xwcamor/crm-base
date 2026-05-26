<?php

namespace App\Http\Requests\BusinessManagement\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditAllUpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase_orders.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('purchase_orders.edit_all_max', 200);

        return [
            'changes'                => "required|array|min:1|max:{$max}",
            'changes.*.id'           => 'required|integer',
            // Campos editables in-line: referencia (texto corto) y estado.
            'changes.*.reference'    => 'sometimes|nullable|string|max:30',
            'changes.*.status'       => ['sometimes', 'nullable', Rule::in(PurchaseOrder::STATUSES)],
        ];
    }
}
