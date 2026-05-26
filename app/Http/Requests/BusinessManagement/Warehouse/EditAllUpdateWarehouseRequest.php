<?php

namespace App\Http\Requests\BusinessManagement\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('warehouses.edit') ?? false;
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('warehouses.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:255',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
