<?php

namespace App\Http\Requests\BusinessManagement\Discount;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $discount  = $this->route('discount');
        $discountId = is_object($discount) ? $discount->id : null;

        return [
            'code' => [
                'required', 'string', 'max:60',
                function ($attribute, $value, $fail) use ($tenantId, $discountId) {
                    $needle = trim((string) $value);
                    $q = DB::table('discounts')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->where('code', $needle)
                        ->when($discountId, fn ($qq) => $qq->where('id', '!=', $discountId));
                    if ($q->exists()) {
                        $fail(__('discounts.code_unique'));
                    }
                },
            ],
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:500'],
            'type'                => ['required', Rule::in(Discount::TYPES)],
            'value'               => ['required', 'numeric', 'min:0'],
            'currency_code'       => ['nullable', 'string', 'size:3'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit'         => ['nullable', 'integer', 'min:0'],
            'usage_per_customer'  => ['nullable', 'integer', 'min:0'],
            'valid_from'          => ['nullable', 'date'],
            'valid_until'         => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }
}
