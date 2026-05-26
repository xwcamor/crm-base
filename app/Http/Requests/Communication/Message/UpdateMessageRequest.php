<?php

namespace App\Http\Requests\Communication\Message;

use App\Models\Message;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super');
    }

    // body se renderiza con v-html; sanitizamos por defensa en profundidad.
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        if (is_string($body)) {
            $this->merge(['body' => HtmlSanitizer::clean($body)]);
        }
    }

    public function rules(): array
    {
        // Bind por slug; el controller resuelve antes de invocar el request.
        $slug = $this->route('slug');
        $currentId = $slug
            ? DB::table('messages')->where('slug', $slug)->value('id')
            : null;

        return [
            'subject' => [
                'required', 'string', 'max:200',
                function ($attribute, $value, $fail) use ($currentId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('messages')->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(subject)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(subject) = LOWER(?)', [$needle]);
                    }
                    if ($currentId) {
                        $q->where('id', '!=', $currentId);
                    }
                    if ($q->exists()) {
                        $fail(__('messages.subject_unique'));
                    }
                },
            ],
            'body'          => ['required', 'string'],
            'audience_type' => ['required', Rule::in(Message::AUDIENCES)],
            'audience_id'   => ['nullable', 'integer', 'required_unless:audience_type,' . Message::AUDIENCE_GLOBAL],
            'allow_replies' => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
            'expires_at'    => ['nullable', 'date'],
            'publish_now'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required'       => __('messages.subject_required'),
            'body.required'          => __('messages.body_required'),
            'audience_type.required' => __('messages.audience_type_required'),
            'audience_id.required_unless' => __('messages.audience_id_required'),
        ];
    }
}
