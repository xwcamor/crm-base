<?php

namespace App\Http\Requests\Communication\Message;

use Illuminate\Foundation\Http\FormRequest;

class EditAllUpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super');
    }

    public function rules(): array
    {
        // edit_all_max define cuantas filas se pueden tocar en un solo batch.
        $max = (int) config('messages.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.subject'   => 'sometimes|nullable|string|max:200',
            'changes.*.is_active' => 'sometimes|nullable|boolean',
        ];
    }
}
