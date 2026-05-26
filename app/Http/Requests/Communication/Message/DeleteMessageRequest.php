<?php

namespace App\Http\Requests\Communication\Message;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super');
    }

    public function rules(): array
    {
        return [
            // Confirmacion por texto: el super tiene que escribir el subject
            // del mensaje para confirmar la baja (mismo patron que Regions).
            'subject_confirmation' => ['required', 'string'],
            'deleted_description'  => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'deleted_description.required' => __('global.delete_reason_required'),
            'deleted_description.min'      => __('global.delete_reason_min_3'),
        ];
    }
}
