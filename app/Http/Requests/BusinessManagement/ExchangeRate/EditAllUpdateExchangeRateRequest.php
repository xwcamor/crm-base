<?php

namespace App\Http\Requests\BusinessManagement\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exchange_rates.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('exchange_rates.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.rate'      => 'sometimes|nullable|numeric|min:0.000001',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
