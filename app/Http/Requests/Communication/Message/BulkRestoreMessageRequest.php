<?php

namespace App\Http\Requests\Communication\Message;

use Illuminate\Foundation\Http\FormRequest;

class BulkRestoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // bulk_restore es operacion super: la ruta vive dentro de role:super.
        return (bool) $this->user()?->hasRole('super');
    }

    public function rules(): array
    {
        return [
            'ids'   => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => __('global.bulk_no_selection'),
        ];
    }
}
