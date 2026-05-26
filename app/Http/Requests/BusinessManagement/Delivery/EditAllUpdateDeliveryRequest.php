<?php

namespace App\Http\Requests\BusinessManagement\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditAllUpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deliveries.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('deliveries.edit_all_max', 200);

        return [
            'changes'              => "required|array|min:1|max:{$max}",
            'changes.*.id'         => 'required|integer',
            'changes.*.reference'  => 'sometimes|nullable|string|max:30',
            'changes.*.status'     => ['sometimes', 'nullable', Rule::in(Delivery::STATUSES)],
            'changes.*.carrier'    => 'sometimes|nullable|string|max:100',
        ];
    }
}
