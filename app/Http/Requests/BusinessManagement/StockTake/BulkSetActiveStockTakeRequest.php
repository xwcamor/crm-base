<?php

namespace App\Http\Requests\BusinessManagement\StockTake;

use App\Models\StockTake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk update de status. A diferencia de Customer (que usa `is_active` boolean),
 * StockTake usa `status` enum. La ruta se llama bulk_set_active por consistencia.
 */
class BulkSetActiveStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_takes.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'status' => ['required', Rule::in(StockTake::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'    => __('global.bulk_no_selection'),
            'status.required' => __('stock_takes.status_required'),
        ];
    }
}
