<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock dashboard — vista pivote de stock por (product × warehouse) y kardex.
 *
 * Solo lectura. Las altas/bajas se generan automáticamente por Sales/Purchase
 * orders en una iteración futura.
 */
class StockController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        $warehouseId = $request->get('warehouse_id');
        $productName = $request->get('product');

        $query = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->select(
                'sl.id', 'sl.qty_on_hand', 'sl.qty_reserved', 'sl.qty_incoming',
                'sl.average_cost', 'sl.last_movement_at',
                'p.id as product_id', 'p.name as product_name', 'p.sku as product_sku',
                'p.low_stock_threshold',
                'w.id as warehouse_id', 'w.name as warehouse_name', 'w.code as warehouse_code',
            )
            ->when($warehouseId, fn ($q) => $q->where('sl.warehouse_id', $warehouseId))
            ->when($productName, fn ($q) => $q->where('p.name', 'like', '%' . $productName . '%'))
            ->orderBy('p.name')->orderBy('w.code');

        $levels = $query->paginate($perPage)->withQueryString();

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($w) => ['value' => $w->id, 'label' => $w->name])->all();

        return inertia('Stock/Index', [
            'levels'             => $levels,
            'warehouseOptions'   => $warehouses,
            'filters' => [
                'warehouse_id' => $warehouseId,
                'product'      => $productName,
                'per_page'     => $perPage,
            ],
        ]);
    }

    public function movements(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);

        $movements = StockMovement::query()
            ->with(['warehouse:id,name,code', 'product:id,name,sku'])
            ->orderBy('moved_at', 'desc')
            ->paginate($perPage)->withQueryString();

        return inertia('Stock/Movements', [
            'movements' => $movements,
            'typeOptions' => collect(StockMovement::TYPES)
                ->map(fn ($t) => ['value' => $t, 'label' => __('stock.type_options.' . $t)])->all(),
        ]);
    }
}
