<?php

namespace App\Http\Requests\BusinessManagement\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $method   = $this->route('payment_method');
        $methodId = is_object($method) ? $method->id : null;

        return [
            'name' => [
                'required', 'string', 'max:100',
                function ($attribute, $value, $fail) use ($tenantId, $methodId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('payment_methods')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($methodId, fn ($qq) => $qq->where('id', '!=', $methodId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('payment_methods.name_unique'));
                    }
                },
            ],
            'code'                 => ['nullable', 'string', 'max:30'],
            'description'          => ['nullable', 'string', 'max:500'],
            'integration_provider' => ['nullable', 'string', 'max:60'],
            'requires_reference'   => ['sometimes', 'boolean'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'is_active'            => ['sometimes', 'boolean'],
        ];
    }
}
