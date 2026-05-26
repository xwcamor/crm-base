<?php

namespace App\Http\Requests\BusinessManagement\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;

class BulkSetActiveExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exchange_rates.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'       => 'required|array|min:1|max:500',
            'ids.*'     => 'integer',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'       => __('global.bulk_no_selection'),
            'is_active.required' => __('exchange_rates.is_active_required'),
        ];
    }
}
