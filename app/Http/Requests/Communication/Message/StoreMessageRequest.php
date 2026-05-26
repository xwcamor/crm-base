<?php

namespace App\Http\Requests\Communication\Message;

use App\Models\Message;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo super crea mensajes. Defense-in-depth: la ruta ya pasa por
        // role:super middleware, pero validamos aqui tambien por si en el
        // futuro alguien mueve el controller fuera de ese grupo.
        return (bool) $this->user()?->hasRole('super');
    }

    // body se renderiza con v-html en el detalle del mensaje; sanitizamos
    // aunque solo super pueda crear, para defensa en profundidad.
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
            'subject' => [
                'required', 'string', 'max:200',
                function ($attribute, $value, $fail) {
                    // subject unico entre los no-borrados — sirve de "name analog".
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('messages')->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(subject)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(subject) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('messages.subject_unique'));
                    }
                },
            ],
            'body'          => ['required', 'string'],
            'audience_type' => ['required', Rule::in(Message::AUDIENCES)],
            // audience_id solo es requerido si la audiencia no es global.
            'audience_id'   => ['nullable', 'integer', 'required_unless:audience_type,' . Message::AUDIENCE_GLOBAL],
            'allow_replies' => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
            'expires_at'    => ['nullable', 'date'],
            // Indicador para distinguir "guardar draft" vs "publicar" desde el form.
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
