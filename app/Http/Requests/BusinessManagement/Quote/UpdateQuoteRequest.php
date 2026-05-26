<?php

namespace App\Http\Requests\BusinessManagement\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $quote   = $this->route('quote');
        $quoteId = is_object($quote) ? $quote->id : null;

        return [
            'name'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
