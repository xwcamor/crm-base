<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk update de status. A diferencia de Customer (que usa `is_active` boolean),
 * Delivery usa `status` enum. La ruta se llama bulk_set_active por consistencia.
 */
class BulkSetActiveDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deliveries.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'status' => ['required', Rule::in(Delivery::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'    => __('global.bulk_no_selection'),
            'status.required' => __('deliveries.status_required'),
        ];
    }
}
