<?php

namespace App\Http\Requests\BusinessManagement\TaxClass;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateTaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax_classes.edit') ?? false;
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('tax_classes.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:255',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
