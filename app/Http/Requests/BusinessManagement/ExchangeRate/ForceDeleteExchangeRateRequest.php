<?php

namespace App\Http\Requests\BusinessManagement\ExchangeRate;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeleteExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            // Para tasas el "name" es la combinacion sintetica
            // "USD/PEN @ 2026-05-19" — usuario debe tipearla exacta.
            'display_confirmation' => 'required|string',
            'reason'               => 'required|string|min:10|max:500',
        ];
    }
}
