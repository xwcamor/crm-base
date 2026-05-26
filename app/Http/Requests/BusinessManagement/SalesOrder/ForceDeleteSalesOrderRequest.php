<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeleteSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            // El usuario tipea la reference de la OV para confirmar.
            'reference_confirmation' => 'required|string',
            'reason'                 => 'required|string|min:10|max:500',
        ];
    }
}
