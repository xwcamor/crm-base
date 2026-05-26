<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeleteDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            // El usuario tipea la reference de la entrega para confirmar.
            'reference_confirmation' => 'required|string',
            'reason'                 => 'required|string|min:10|max:500',
        ];
    }
}
