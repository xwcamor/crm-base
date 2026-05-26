<?php

namespace App\Http\Requests\BusinessManagement\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('base_code')) {
            $this->merge(['base_code' => strtoupper(trim((string) $this->base_code))]);
        }
        if ($this->filled('quote_code')) {
            $this->merge(['quote_code' => strtoupper(trim((string) $this->quote_code))]);
        }
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $rate      = $this->route('exchange_rate');
        $rateId    = is_object($rate) ? $rate->id : null;

        return [
            'base_code' => [
                'required', 'string', 'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'quote_code' => [
                'required', 'string', 'size:3',
                'regex:/^[A-Z]{3}$/',
                'different:base_code',
            ],
            'rate'     => ['required', 'numeric', 'min:0.000001'],
            'valid_at' => [
                'required', 'date',
                function ($attribute, $value, $fail) use ($tenantId, $rateId) {
                    $base  = $this->input('base_code');
                    $quote = $this->input('quote_code');
                    if (!$base || !$quote) return;

                    $q = DB::table('exchange_rates')
                        ->whereNull('deleted_at')
                        ->where('base_code',  $base)
                        ->where('quote_code', $quote)
                        ->where('valid_at',   $value)
                        ->when($rateId, fn ($qq) => $qq->where('id', '!=', $rateId));
                    if ($tenantId !== null) {
                        $q->where('tenant_id', $tenantId);
                    } else {
                        $q->whereNull('tenant_id');
                    }
                    if ($q->exists()) {
                        $fail(__('exchange_rates.pair_valid_unique'));
                    }
                },
            ],
            'source'    => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
