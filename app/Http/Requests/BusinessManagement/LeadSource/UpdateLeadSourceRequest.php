<?php

namespace App\Http\Requests\BusinessManagement\LeadSource;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateLeadSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $leadSource = $this->route('lead_source');
        $leadSourceId = is_object($leadSource) ? $leadSource->id : null;

        return [
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) use ($tenantId, $leadSourceId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('lead_sources')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($leadSourceId, fn ($qq) => $qq->where('id', '!=', $leadSourceId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('lead_sources.name_unique'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'category'    => ['nullable', 'string', 'max:60'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
