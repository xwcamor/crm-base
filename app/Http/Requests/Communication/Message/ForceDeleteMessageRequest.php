<?php

namespace App\Http\Requests\Communication\Message;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super');
    }

    public function rules(): array
    {
        return [
            // subject_confirmation porque Messages no tiene `name`; subject
            // funciona como identificador "humano" del registro.
            'subject_confirmation' => 'required|string',
            'reason'               => 'required|string|min:10|max:500',
        ];
    }
}
