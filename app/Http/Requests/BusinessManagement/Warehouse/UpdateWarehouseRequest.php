<?php

namespace App\Http\Requests\BusinessManagement\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId   = $this->user()?->tenant_id;
        $warehouse   = $this->route('warehouse');
        $warehouseId = is_object($warehouse) ? $warehouse->id : null;

        return [
            'name'            => ['required', 'string', 'max:255'],
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
