<?php

namespace App\Http\Requests\BusinessManagement\LeadSource;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateLeadSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lead_sources.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('lead_sources.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:120',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
