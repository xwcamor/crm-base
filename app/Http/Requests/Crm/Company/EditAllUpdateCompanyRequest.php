<?php

namespace App\Http\Requests\Crm\Company;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('companies.edit') ?? false;
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('companies.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:255',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
