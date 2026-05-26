<?php

namespace App\Http\Requests\BusinessManagement\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeletePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        return [
            // Para PurchaseOrders pedimos confirmacion de `reference` (no `name`,
            // que el modelo no tiene). Es el identificador legible para el user.
            'reference_confirmation' => 'required|string',
            'reason'                 => 'required|string|min:10|max:500',
        ];
    }
}
