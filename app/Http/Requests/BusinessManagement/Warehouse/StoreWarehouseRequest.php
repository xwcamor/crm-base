<?php

namespace App\Http\Requests\BusinessManagement\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
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
                    $q = DB::table('warehouses')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('warehouses.name_unique'));
                    }
                },
            ],
            'description'     => ['nullable', 'string', 'max:1000'],
            'code'            => ['required', 'string', 'max:30'],
            'address_line'    => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:100'],
            'country_id'      => ['nullable', 'integer', 'exists:countries,id'],
            'type'            => ['required', Rule::in(\App\Models\Warehouse::TYPES)],
            'is_default'      => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
