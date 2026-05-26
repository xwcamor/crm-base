<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deliveries.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }
}
