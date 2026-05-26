<?php

namespace App\Http\Requests\BusinessManagement\PriceList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $priceList = $this->route('price_list');
        $priceListId = is_object($priceList) ? $priceList->id : null;

        return [
            'name' => [
                'required', 'string', 'max:150',
                function ($attribute, $value, $fail) use ($tenantId, $priceListId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('price_lists')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($priceListId, fn ($qq) => $qq->where('id', '!=', $priceListId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('price_lists.name_unique'));
                    }
                },
            ],
            'description'         => ['nullable', 'string', 'max:500'],
            'currency_code'       => ['nullable', 'string', 'size:3'],
            'valid_from'          => ['nullable', 'date'],
            'valid_until'         => ['nullable', 'date', 'after_or_equal:valid_from'],
            'global_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority'            => ['nullable', 'integer', 'min:0'],
            'is_default'          => ['sometimes', 'boolean'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }
}
