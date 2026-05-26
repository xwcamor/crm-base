<?php

namespace App\Http\Requests\BusinessManagement\StockTake;

use App\Models\StockTake;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_takes.create') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            // Reference es unique-per-tenant (case insensitive). Identificacion
            // humana del conteo — equivalente a `name` en Customer.
            'reference'    => [
                'nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value === null || $value === '') return;
                    $isPgsql = DB::getDriverName() === 'pgsql';
                    $needle  = trim((string) $value);
                    $q = DB::table('stock_takes')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at');
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
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'status'       => ['required', Rule::in(StockTake::STATUSES)],
            'note'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
