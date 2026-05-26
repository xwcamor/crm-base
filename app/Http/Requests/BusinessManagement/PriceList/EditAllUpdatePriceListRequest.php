<?php

namespace App\Http\Requests\BusinessManagement\PriceList;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('price_lists.edit') ?? false;
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('price_lists.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:150',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
