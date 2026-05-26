<?php

namespace App\Http\Requests\BusinessManagement\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_methods.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('payment_methods.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:100',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
