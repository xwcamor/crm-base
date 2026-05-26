<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_orders.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }
}
