<?php

namespace App\Http\Requests\BusinessManagement\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $customer   = $this->route('customer');
        $customerId = is_object($customer) ? $customer->id : null;

        return [
            'name'       => ['required', 'string', 'max:255'],
            // @scaffold:anchor description-rule
            // @scaffold:remove-begin commercial-rules
            'cod'        => [
                'nullable', 'string', 'max:50',
                Rule::unique('customers', 'cod')
                    ->ignore($customerId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            // @scaffold:remove-end
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
