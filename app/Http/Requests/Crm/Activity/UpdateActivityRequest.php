<?php

namespace App\Http\Requests\Crm\Activity;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'              => ['required', Rule::in(Activity::TYPES)],
            'subject'           => ['nullable', 'string', 'max:200'],
            'body'              => ['nullable', 'string', 'max:10000'],

            'due_at'            => ['nullable', 'date'],
            'completed_at'      => ['nullable', 'date'],

            'outcome'           => ['nullable', Rule::in(Activity::CALL_OUTCOMES)],
            'duration_min'      => ['nullable', 'integer', 'min:0', 'max:99999'],
            'location'          => ['nullable', 'string', 'max:500'],
            'priority'          => ['nullable', Rule::in(Activity::PRIORITIES)],
            'attachment'        => ['nullable', 'file', 'max:10240'],
            'related_quote_id'  => ['nullable', 'integer', 'exists:quotes,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $type = $this->input('type');
            $require = function (string $field) use ($v) {
                if (blank($this->input($field))) {
                    $v->errors()->add($field, __('activities.field_required'));
                }
            };
            switch ($type) {
                case 'note':    $require('body'); break;
                case 'call':    $require('body'); $require('outcome'); break;
                case 'email':   $require('subject'); $require('body'); break;
                case 'meeting': $require('subject'); $require('due_at'); break;
                case 'task':    $require('subject'); $require('due_at'); break;
            }
        });
    }
}
