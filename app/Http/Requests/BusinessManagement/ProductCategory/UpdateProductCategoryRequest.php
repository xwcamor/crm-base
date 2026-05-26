<?php

namespace App\Http\Requests\BusinessManagement\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $category  = $this->route('product_category');
        $categoryId = is_object($category) ? $category->id : null;

        return [
            'name' => [
                'required', 'string', 'max:150',
                function ($attribute, $value, $fail) use ($tenantId, $categoryId) {
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $parentId = $this->input('parent_id');
                    $q = DB::table('product_categories')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($categoryId, fn ($qq) => $qq->where('id', '!=', $categoryId));
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
            'parent_id'   => [
                'nullable', 'integer', 'exists:product_categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    if ($value && $categoryId && (int) $value === (int) $categoryId) {
                        $fail(__('product_categories.parent_self'));
                    }
                },
            ],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
