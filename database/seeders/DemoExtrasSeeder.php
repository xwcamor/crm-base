<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Discount;
use App\Models\ExchangeRate;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Models\StockLot;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoExtrasSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Exchange rates GLOBALES (sin tenant) ──────────────────────────
        if (ExchangeRate::count() === 0) {
            $rates = [
                // base → quote → rate. Centrado en pares mas usados en Peru:
                // USD/PEN (principal), EUR/PEN, BRL/PEN (comercio frontera),
                // mas referencia USD vs majors globales.
                ['USD','PEN',3.785000], ['PEN','USD',0.264148],
                ['USD','EUR',0.927400], ['EUR','USD',1.078280],
                ['USD','BRL',5.140000], ['BRL','USD',0.194553],
                ['EUR','PEN',4.082000], ['PEN','EUR',0.244978],
                ['BRL','PEN',0.736500], ['PEN','BRL',1.357770],
                ['USD','MXN',17.420000], ['MXN','USD',0.057405],
                ['USD','CLP',945.800000], ['CLP','USD',0.001057],
            ];
            foreach ($rates as [$base, $quote, $rate]) {
                ExchangeRate::create([
                    'base_code' => $base, 'quote_code' => $quote, 'rate' => $rate,
                    'valid_at' => Carbon::now()->subDays(rand(0, 2))->toDateTimeString(),
                    'source' => 'manual',
                ]);
            }
            $this->command?->info("  · Exchange rates: " . count($rates) . " globales");
        }

        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();
        foreach ($tenants as $tenant) {
            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('email', 'not like', 'api+%')
                ->orderBy('id')->first();
            if (!$admin) continue;

            DB::transaction(fn () => $this->seedForTenant($tenant, $admin));
            $this->command?->info("  · {$tenant->name}: extras (discounts/variants/price_lists/deliveries/stock_takes/lots)");
        }
    }

    protected function seedForTenant(Tenant $tenant, User $admin): void
    {
        $currency = $tenant->default_currency_code ?? 'USD';

        // ─── Discounts (cupones) ───────────────────────────────────────────
        if (Discount::where('tenant_id', $tenant->id)->doesntExist()) {
            $now = Carbon::now();
            $discounts = [
                ['code' => 'WELCOME10',  'name' => 'Bienvenida 10%',     'type' => 'percentage',  'value' => 10, 'usage_limit' => 100, 'min_purchase_amount' => 50],
                ['code' => 'BLACKFRIDAY','name' => 'Black Friday 25%',   'type' => 'percentage',  'value' => 25, 'usage_limit' => 500, 'valid_from' => $now->copy()->subDays(2), 'valid_until' => $now->copy()->addDays(5)],
                ['code' => 'FIX100',     'name' => 'Descuento fijo 100', 'type' => 'fixed_amount','value' => 100, 'currency_code' => $currency],
                ['code' => 'FREESHIP',   'name' => 'Envío gratis',       'type' => 'free_shipping','value' => 0, 'min_purchase_amount' => 200],
                ['code' => 'VIP50',      'name' => 'VIP 50% off',        'type' => 'percentage',  'value' => 50, 'usage_per_customer' => 1],
            ];
            foreach ($discounts as $d) {
                Discount::create(array_merge($d, [
                    'slug' => Str::random(22), 'is_active' => true, 'usage_count' => rand(0, 30),
                    'tenant_id' => $tenant->id, 'created_by' => $admin->id,
                ]));
            }
        }

        // ─── Price lists ───────────────────────────────────────────────────
        if (PriceList::where('tenant_id', $tenant->id)->doesntExist()) {
            $lists = [
                ['name' => 'Lista Retail Standard', 'global_discount_pct' => 0,  'is_default' => true,  'priority' => 100],
                ['name' => 'Wholesale 25% off',     'global_discount_pct' => 25, 'is_default' => false, 'priority' => 50],
                ['name' => 'Enterprise Q4 2026',    'global_discount_pct' => 15, 'is_default' => false, 'priority' => 30, 'valid_from' => Carbon::now()->subDays(10), 'valid_until' => Carbon::now()->addDays(60)],
                ['name' => 'Partner LATAM',         'global_discount_pct' => 35, 'is_default' => false, 'priority' => 20],
            ];
            $products = Product::where('tenant_id', $tenant->id)->where('is_active', true)->limit(8)->get();
            foreach ($lists as $l) {
                $pl = PriceList::create(array_merge($l, [
                    'slug' => Str::random(22), 'is_active' => true, 'currency_code' => $currency,
                    'tenant_id' => $tenant->id, 'created_by' => $admin->id,
                ]));
                // 3 items overrides por lista
                foreach ($products->random(min(3, $products->count())) as $p) {
                    PriceListItem::create([
                        'price_list_id' => $pl->id,
                        'product_id'    => $p->id,
                        'discount_pct'  => rand(5, 30),
                        'min_quantity'  => rand(1, 5),
                        'is_active'     => true,
                        'created_by'    => $admin->id,
                    ]);
                }
            }
        }

        // ─── Product variants (para los productos físicos) ─────────────────
        if (ProductVariant::where('tenant_id', $tenant->id)->doesntExist()) {
            $physicalProducts = Product::where('tenant_id', $tenant->id)
                ->where('type', 'good')->where('track_inventory', true)->limit(3)->get();
            foreach ($physicalProducts as $p) {
                $colors = ['Negro', 'Blanco', 'Plata'];
                foreach ($colors as $idx => $color) {
                    ProductVariant::create([
                        'slug' => Str::random(22),
                        'product_id' => $p->id,
                        'sku'   => $p->sku . '-' . strtoupper(substr($color, 0, 3)),
                        'name'  => $p->name . ' — ' . $color,
                        'attributes' => ['color' => $color],
                        'price' => (float) $p->list_price,
                        'cost'  => (float) ($p->cost ?? $p->list_price * 0.4),
                        'low_stock_threshold' => 3,
                        'is_active' => true,
                        'sort_order' => $idx,
                        'tenant_id'  => $tenant->id,
                        'created_by' => $admin->id,
                    ]);
                }
            }
        }

        // ─── Stock lots (para 2 productos perecederos demo) ────────────────
        if (StockLot::where('tenant_id', $tenant->id)->doesntExist()) {
            $defaultWh = Warehouse::where('tenant_id', $tenant->id)->where('is_default', true)->first();
            if ($defaultWh) {
                $sampleProducts = Product::where('tenant_id', $tenant->id)->where('type', 'good')->limit(2)->get();
                foreach ($sampleProducts as $p) {
                    for ($i = 1; $i <= 2; $i++) {
                        $qtyInit = rand(20, 80);
                        $remaining = $qtyInit - rand(0, 10);
                        StockLot::create([
                            'product_id'     => $p->id,
                            'warehouse_id'   => $defaultWh->id,
                            'lot_number'     => sprintf('LOT-%s-%02d-2026', $p->sku, $i),
                            'qty_initial'    => $qtyInit,
                            'qty_remaining'  => $remaining,
                            'manufactured_at'=> Carbon::now()->subMonths(rand(1, 4))->toDateString(),
                            'expires_at'     => Carbon::now()->addMonths(rand(6, 18))->toDateString(),
                            'unit_cost'      => (float) ($p->cost ?? $p->list_price * 0.4),
                            'is_active'      => true,
                            'tenant_id'      => $tenant->id,
                            'created_by'     => $admin->id,
                        ]);
                    }
                }
            }
        }

        // ─── Deliveries (de las sales orders shipped) ──────────────────────
        if (Delivery::where('tenant_id', $tenant->id)->doesntExist()) {
            $defaultWh = Warehouse::where('tenant_id', $tenant->id)->where('is_default', true)->first();
            $shippedSO = SalesOrder::where('tenant_id', $tenant->id)
                ->where('status', 'shipped')->with('items')->limit(3)->get();
            $counter = 1;
            foreach ($shippedSO as $so) {
                $del = Delivery::create([
                    'slug'           => Str::random(22),
                    'reference'      => sprintf('ENT-2026-%04d', $counter++),
                    'sales_order_id' => $so->id,
                    'warehouse_id'   => $defaultWh?->id ?? $so->warehouse_id,
                    'status'         => 'delivered',
                    'expected_delivery_date' => $so->expected_delivery_date,
                    'shipped_at'     => $so->shipped_at,
                    'delivered_at'   => $so->shipped_at ? Carbon::parse($so->shipped_at)->addDays(2)->toDateTimeString() : Carbon::now()->subDays(rand(1,5))->toDateTimeString(),
                    'signed_by_name' => 'Recepción ' . ($so->company?->name ?? '-'),
                    'carrier'        => ['DHL', 'FedEx', 'Olva Courier', 'OCA'][rand(0,3)],
                    'tracking_number'=> 'TRK' . rand(1000000, 9999999),
                    'shipping_cost'  => 25.00,
                    'tenant_id'      => $tenant->id,
                    'created_by'     => $admin->id,
                ]);
                foreach ($so->items as $it) {
                    DeliveryItem::create([
                        'delivery_id'         => $del->id,
                        'sales_order_item_id' => $it->id,
                        'product_id'          => $it->product_id,
                        'quantity'            => $it->quantity_ordered,
                    ]);
                }
            }
        }

        // ─── Stock Takes (1 completado, 1 in_progress) ─────────────────────
        if (StockTake::where('tenant_id', $tenant->id)->doesntExist()) {
            $warehouses = Warehouse::where('tenant_id', $tenant->id)->where('is_active', true)->limit(2)->get();
            $plans = [
                ['status' => 'completed',   'days_ago' => 30],
                ['status' => 'in_progress', 'days_ago' => 2],
            ];
            $counter = 1;
            foreach ($plans as $i => $plan) {
                $wh = $warehouses[$i] ?? $warehouses->first();
                if (!$wh) continue;
                $take = StockTake::create([
                    'slug'        => Str::random(22),
                    'reference'   => sprintf('COUNT-2026-%04d', $counter++),
                    'warehouse_id'=> $wh->id,
                    'status'      => $plan['status'],
                    'started_at'  => Carbon::now()->subDays($plan['days_ago'])->toDateTimeString(),
                    'completed_at'=> $plan['status'] === 'completed' ? Carbon::now()->subDays($plan['days_ago'] - 1)->toDateTimeString() : null,
                    'completed_by'=> $plan['status'] === 'completed' ? $admin->id : null,
                    'note'        => $plan['status'] === 'completed' ? 'Conteo trimestral cerrado sin novedades grandes.' : 'En proceso — pendiente conteo zona B.',
                    'tenant_id'   => $tenant->id,
                    'created_by'  => $admin->id,
                ]);

                $levels = StockLevel::where('warehouse_id', $wh->id)->limit(8)->get();
                foreach ($levels as $sl) {
                    $sys = (float) $sl->qty_on_hand;
                    if ($plan['status'] === 'completed') {
                        $counted = $sys + (rand(-3, 2));   // pequeñas varianzas
                        $variance = $counted - $sys;
                    } else {
                        $counted = null;
                        $variance = 0;
                    }
                    StockTakeLine::create([
                        'stock_take_id' => $take->id,
                        'product_id'    => $sl->product_id,
                        'qty_system'    => $sys,
                        'qty_counted'   => $counted,
                        'variance'      => $variance,
                    ]);
                }
            }
        }
    }
}
