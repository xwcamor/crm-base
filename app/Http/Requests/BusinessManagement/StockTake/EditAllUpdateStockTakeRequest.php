<?php

namespace App\Http\Requests\BusinessManagement\StockTake;

use App\Models\StockTake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditAllUpdateStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_takes.edit') ?? false;
    }

    public function rules(): array
    {
        $max = (int) config('stock_takes.edit_all_max', 200);

        return [
            'changes'             => "required|array|min:1|max:{$max}",
            'changes.*.id'        => 'required|integer',
            'changes.*.reference' => 'sometimes|nullable|string|max:30',
            'changes.*.status'    => ['sometimes', 'nullable', Rule::in(StockTake::STATUSES)],
        ];
    }
}
