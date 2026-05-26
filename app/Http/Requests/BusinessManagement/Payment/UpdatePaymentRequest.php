<?php

namespace App\Http\Requests\BusinessManagement\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $payment   = $this->route('payment');
        $paymentId = is_object($payment) ? $payment->id : null;

        return [
            'name'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
