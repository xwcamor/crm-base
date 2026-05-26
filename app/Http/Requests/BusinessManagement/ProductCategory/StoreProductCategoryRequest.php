<?php

namespace App\Http\Requests\BusinessManagement\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required', 'string', 'max:150',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $parentId = $this->input('parent_id');
                    $q = DB::table('product_categories')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
                    if ($parentId) {
                        $q->where('parent_id', $parentId);
                    } else {
                        $q->whereNull('parent_id');
                    }
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(name) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('product_categories.name_unique'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id'   => ['nullable', 'integer', 'exists:product_categories,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
