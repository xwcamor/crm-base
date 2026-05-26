<?php

namespace App\Http\Requests\Communication\Message;

use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquier user autenticado puede intentar responder; el controller
        // valida que sea recipient del mensaje y que allow_replies = true.
        return $this->user() !== null;
    }

    // El body se renderiza en el cliente con v-html. Sanitizamos antes de
    // validar para neutralizar XSS (script/iframe/onerror/href javascript:).
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        if (is_string($body)) {
            $this->merge(['body' => HtmlSanitizer::clean($body)]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('messages.reply_body_required'),
            'body.max'      => __('messages.reply_body_max'),
        ];
    }
}
