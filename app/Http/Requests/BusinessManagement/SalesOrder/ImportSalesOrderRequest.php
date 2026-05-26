<?php

namespace App\Http\Requests\BusinessManagement\SalesOrder;

use Illuminate\Foundation\Http\FormRequest;

class ImportSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales_orders.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'file'    => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'mode'    => 'nullable|in:create_only,update_or_create',
            'dry_run' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('imports.file_required'),
            'file.mimes'    => __('imports.file_mimes'),
            'file.max'      => __('imports.file_max'),
        ];
    }
}
