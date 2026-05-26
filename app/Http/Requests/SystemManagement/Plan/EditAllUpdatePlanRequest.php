<?php

namespace App\Http\Requests\SystemManagement\Plan;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('plans.edit_all_max', 100);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.name'      => 'sometimes|nullable|string|max:100',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
