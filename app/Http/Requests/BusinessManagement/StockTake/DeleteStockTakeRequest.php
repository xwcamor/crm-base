<?php

namespace App\Http\Requests\BusinessManagement\StockTake;

use Illuminate\Foundation\Http\FormRequest;

class DeleteStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_takes.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'deleted_description' => 'required|string|min:3|max:1000',
        ];
    }
}
