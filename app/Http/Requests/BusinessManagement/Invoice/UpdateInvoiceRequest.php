<?php

namespace App\Http\Requests\BusinessManagement\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $invoice   = $this->route('invoice');
        $invoiceId = is_object($invoice) ? $invoice->id : null;

        return [
            'name'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
