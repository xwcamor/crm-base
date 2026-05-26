<?php

namespace App\Http\Requests\BusinessManagement\TaxClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreTaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // Unicidad case + accent insensitive dentro del workspace.
            // "Acme S.A." y "ACME S.A." se consideran duplicados — mismo
            // patron que Regions/Languages/etc. El constraint UNIQUE de la
            // BD es el ultimo guardrail; esta validacion lo detecta antes.
            'name'       => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('tax_classes')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('tax_classes.name_unique'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'code'        => ['nullable', 'string', 'max:30'],
            'is_default'  => ['sometimes', 'boolean'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
