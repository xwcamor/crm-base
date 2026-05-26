<?php

namespace App\Http\Requests\BusinessManagement\StockTake;

use App\Models\StockTake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_takes.edit') ?? false;
    }

    public function rules(): array
    {
        $tenantId  = $this->user()?->tenant_id;
        $stockTake = $this->route('stock_take');
        $stockTakeId = is_object($stockTake) ? $stockTake->id : null;

        return [
            'reference'           => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId, $stockTakeId) {
                    if ($value === null || $value === '') return;
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('stock_takes')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->when($stockTakeId, fn ($qq) => $qq->where('id', '!=', $stockTakeId));
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(LOWER(reference)) = unaccent(LOWER(?))', [$needle]);
                    } else {
                        $q->whereRaw('LOWER(reference) = LOWER(?)', [$needle]);
                    }
                    if ($q->exists()) {
                        $fail(__('stock_takes.reference_unique'));
                    }
                },
            ],
            'status'              => ['required', Rule::in(StockTake::STATUSES)],
            'note'                => ['nullable', 'string', 'max:1000'],
            'lines'               => ['nullable', 'array'],
            'lines.*.id'          => ['required', 'integer', 'exists:stock_take_lines,id'],
            'lines.*.qty_counted' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
