<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TaxClass;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();

        foreach ($tenants as $tenant) {
            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', 'not like', 'api+%')
                ->orderBy('id')->first();
            if (!$admin) continue;

            // Idempotencia granular: cada chunk se chequea por separado en
            // seedForTenant(). Asi un tenant con warehouses pero sin SO/PO
            // puede completar el resto sin tener que borrar nada.
            DB::transaction(fn () => $this->seedForTenant($tenant, $admin));
        }
    }

    protected function seedForTenant(Tenant $tenant, User $admin): void
    {
        $currency = $tenant->default_currency_code ?? 'USD';

        // ─── Tax classes ──────────────────────────────────────────────────
        // Todos los tenants demo son peruanos: usamos IGV (Impuesto General a
        // las Ventas) 18% como tasa standard. Reducido + exento + percepción
        // como tasas adicionales realistas para distintos rubros.
        $taxes = collect([
            ['name' => 'IGV 18% Standard',     'code' => 'IGV-18',  'is_default' => true],
            ['name' => 'IGV 10% Reducido',     'code' => 'IGV-10',  'is_default' => false],
            ['name' => 'IGV 0% Exonerado',     'code' => 'IGV-0',   'is_default' => false],
            ['name' => 'Percepción IGV 2%',    'code' => 'PERC-2',  'is_default' => false],
        ])->map(fn ($t) => TaxClass::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $t['name']],
            ['slug' => Str::random(22), 'code' => $t['code'], 'is_default' => $t['is_default'], 'is_active' => true, 'created_by' => $admin->id]
        ));

        // ─── Warehouses ────────────────────────────────────────────────────
        // Demo data: tenant 1 → Lima (capital), tenant 2 → Trujillo/norte.
        // El switch por tenant_id (no por currency) deja ambos en Perú.
        $whData = $tenant->id === 1
            ? [
                ['code' => 'LIM-CENTRAL', 'name' => 'Almacén Central Lima',   'type' => 'main',    'city' => 'Lima',     'is_default' => true],
                ['code' => 'LIM-SUR',     'name' => 'Sucursal Lima Sur',      'type' => 'branch',  'city' => 'Lima',     'is_default' => false],
                ['code' => 'AQP',         'name' => 'Depósito Arequipa',      'type' => 'branch',  'city' => 'Arequipa', 'is_default' => false],
                ['code' => 'VIRT-EXP',    'name' => 'Virtual — Exportación',  'type' => 'virtual', 'city' => null,       'is_default' => false],
            ]
            : [
                ['code' => 'TRU-CENT',    'name' => 'Almacén Central Trujillo','type' => 'main',    'city' => 'Trujillo', 'is_default' => true],
                ['code' => 'CHI',         'name' => 'Sucursal Chiclayo',       'type' => 'branch',  'city' => 'Chiclayo', 'is_default' => false],
                ['code' => 'PIU',         'name' => 'Depósito Piura',          'type' => 'branch',  'city' => 'Piura',    'is_default' => false],
                ['code' => 'CUS',         'name' => 'Depósito Cusco',          'type' => 'branch',  'city' => 'Cusco',    'is_default' => false],
            ];

        // firstOrCreate por (tenant_id, code) → idempotente. Si ya existe el
        // warehouse, lo retorna sin duplicar. Asi podemos re-correr el seeder
        // sin error cuando ya hay warehouses.
        $warehouses = collect();
        foreach ($whData as $w) {
            $warehouses->push(Warehouse::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $w['code']],
                array_merge($w, [
                    'slug'            => Str::random(22),
                    'created_by'      => $admin->id,
                    'manager_user_id' => $admin->id,
                    'is_active'       => true,
                ])
            ));
        }
        $this->command?->info("  - {$tenant->name}: warehouses OK ({$warehouses->count()}).");

        // ─── Stock levels: distribuir productos físicos entre warehouses ──
        // Idempotente: skip si ya hay stock_levels para este tenant.
        $hasStock = StockLevel::where('tenant_id', $tenant->id)->exists();
        if ($hasStock) {
            $this->command?->warn("  - {$tenant->name}: stock ya existe, salteado.");
        } else {
        $physicalProducts = Product::where('tenant_id', $tenant->id)
            ->where('type', 'good')->where('track_inventory', true)->get();

        $movements = [];
        foreach ($physicalProducts as $product) {
            foreach ($warehouses as $idx => $wh) {
                // Skip virtual warehouses para no inflar stock
                if ($wh->type === 'virtual') continue;
                // Stock random pero realista
                $qty = $idx === 0 ? rand(20, 200) : rand(0, 50);
                if ($qty === 0 && $idx > 1) continue; // No crear rows vacías para algunos
                $cost = (float)($product->cost ?? ($product->list_price * 0.4));
                StockLevel::create([
                    'warehouse_id'     => $wh->id,
                    'product_id'       => $product->id,
                    'qty_on_hand'      => $qty,
                    'qty_reserved'     => $idx === 0 ? min($qty, rand(0, 5)) : 0,
                    'qty_incoming'     => 0,
                    'average_cost'     => $cost,
                    'last_movement_at' => Carbon::now()->subDays(rand(1, 30)),
                    'tenant_id'        => $tenant->id,
                ]);
                if ($qty > 0) {
                    $movements[] = [
                        'warehouse_id' => $wh->id, 'product_id' => $product->id,
                        'type' => 'receipt', 'quantity' => $qty, 'unit_cost' => $cost,
                        'total_cost' => $qty * $cost, 'source_reference' => 'OPENING-BAL',
                        'moved_at' => Carbon::now()->subDays(rand(15, 60)),
                        'note' => 'Saldo inicial de inventario',
                        'tenant_id' => $tenant->id, 'created_by' => $admin->id,
                    ];
                }
            }
        }
        foreach ($movements as $m) {
            StockMovement::create($m);
        }
        $this->command?->info("  - {$tenant->name}: stock levels OK.");
        } // end if !$hasStock

        // ─── Sales Orders (a partir de accepted quotes) ─────────────────────
        // Idempotente: skip si ya hay sales_orders para este tenant.
        $defaultWh = $warehouses->where('is_default', true)->first() ?? $warehouses->first();
        if (SalesOrder::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->warn("  - {$tenant->name}: sales orders ya existen, salteado.");
        } else {
        $acceptedQuotes = Quote::where('tenant_id', $tenant->id)->where('status', 'accepted')->with('items')->get();
        $soCounter = 1;

        foreach ($acceptedQuotes as $quote) {
            $orderDate = $quote->accepted_at ? Carbon::parse($quote->accepted_at) : Carbon::now()->subDays(15);
            $order = SalesOrder::create([
                'slug'           => Str::random(22),
                'prefix'         => 'OV',
                'reference'      => sprintf('OV-2026-%04d', $soCounter++),
                'quote_id'       => $quote->id,
                'company_id'     => $quote->company_id,
                'contact_id'     => $quote->contact_id,
                'status'         => 'shipped',
                'warehouse_id'   => $defaultWh->id,
                'order_date'     => $orderDate->toDateString(),
                'expected_delivery_date' => $orderDate->copy()->addDays(7)->toDateString(),
                'shipped_at'     => $orderDate->copy()->addDays(5)->toDateTimeString(),
                'currency_code'  => $quote->currency_code,
                'subtotal'       => $quote->subtotal,
                'discount_total' => $quote->discount_total,
                'tax_total'      => $quote->tax_total,
                'shipping_cost'  => $quote->shipping_cost,
                'grand_total'    => $quote->grand_total,
                'payment_terms_days' => 30,
                'payment_status' => 'paid',
                'owner_id'       => $admin->id,
                'tenant_id'      => $tenant->id,
                'created_by'     => $admin->id,
            ]);
            foreach ($quote->items as $qi) {
                SalesOrderItem::create([
                    'sales_order_id'     => $order->id,
                    'product_id'         => $qi->product_id,
                    'name'               => $qi->name,
                    'sku'                => $qi->sku,
                    'quantity_ordered'   => $qi->quantity,
                    'quantity_fulfilled' => $qi->quantity,
                    'unit_price'         => $qi->unit_price,
                    'discount_pct'       => $qi->discount_pct,
                    'tax_pct'            => $qi->tax_pct,
                    'line_subtotal'      => $qi->line_subtotal,
                    'line_tax'           => $qi->line_tax,
                    'line_total'         => $qi->line_total,
                    'sort_order'         => $qi->sort_order,
                ]);
            }
        }

        // 2 OV pendientes adicionales
        $companies = Company::where('tenant_id', $tenant->id)->whereIn('company_type', ['customer','prospect'])->limit(2)->get();
        foreach ($companies as $idx => $company) {
            $products = $physicalProducts->random(min(2, $physicalProducts->count()));
            if ($products->isEmpty()) continue;
            $subtotal = 0;
            $items = [];
            foreach ($products as $j => $p) {
                $qty = rand(1, 3);
                $lineSub = round($qty * (float)$p->list_price, 2);
                $lineTax = round($lineSub * 0.18, 2);
                $items[] = [
                    'product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
                    'quantity_ordered' => $qty, 'quantity_fulfilled' => 0,
                    'unit_price' => $p->list_price, 'tax_pct' => 18,
                    'line_subtotal' => $lineSub, 'line_tax' => $lineTax,
                    'line_total' => round($lineSub + $lineTax, 2), 'sort_order' => $j,
                ];
                $subtotal += $lineSub;
            }
            $order = SalesOrder::create([
                'slug'           => Str::random(22),
                'prefix'         => 'OV',
                'reference'      => sprintf('OV-2026-%04d', $soCounter++),
                'company_id'     => $company->id,
                'status'         => $idx === 0 ? 'pending' : 'processing',
                'warehouse_id'   => $defaultWh->id,
                'order_date'     => Carbon::now()->subDays(rand(1, 5))->toDateString(),
                'expected_delivery_date' => Carbon::now()->addDays(7)->toDateString(),
                'currency_code'  => $currency,
                'subtotal'       => $subtotal,
                'tax_total'      => round($subtotal * 0.18, 2),
                'grand_total'    => round($subtotal * 1.18, 2),
                'payment_terms_days' => 30,
                'payment_status' => 'unpaid',
                'owner_id'       => $admin->id,
                'tenant_id'      => $tenant->id,
                'created_by'     => $admin->id,
            ]);
            foreach ($items as $it) {
                SalesOrderItem::create(array_merge($it, ['sales_order_id' => $order->id]));
            }
        }
        $this->command?->info("  - {$tenant->name}: sales orders OK.");
        } // end if !SalesOrder exists

        // ─── Purchase Orders a suppliers ───────────────────────────────────
        // Idempotente: skip si ya hay purchase_orders para este tenant.
        if (PurchaseOrder::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->warn("  - {$tenant->name}: purchase orders ya existen, salteado.");
            return;
        }

        // Necesitamos $physicalProducts en este chunk tambien — re-fetch.
        $physicalProducts = Product::where('tenant_id', $tenant->id)
            ->where('type', 'good')->where('track_inventory', true)->get();

        $suppliers = Company::where('tenant_id', $tenant->id)
            ->whereIn('company_type', ['supplier', 'both', 'partner'])->limit(2)->get();
        if ($suppliers->isEmpty()) {
            // Si no hay suppliers, marcar 2 customers como "both" para que actúen como suppliers de demo
            $borrow = Company::where('tenant_id', $tenant->id)->limit(2)->get();
            $borrow->each(fn ($c) => $c->update(['company_type' => 'both']));
            $suppliers = $borrow;
        }

        $poCounter = 1;
        $poPlans = [
            ['status' => 'received', 'days_ago' => 20],
            ['status' => 'confirmed', 'days_ago' => 5],
            ['status' => 'draft',     'days_ago' => 1],
        ];
        foreach ($poPlans as $i => $plan) {
            $supplier = $suppliers[$i % $suppliers->count()];
            $products = $physicalProducts->random(min(3, $physicalProducts->count()));
            if ($products->isEmpty()) continue;
            $subtotal = 0;
            $itemsData = [];
            foreach ($products as $j => $p) {
                $qty = rand(10, 50);
                $cost = (float)($p->cost ?? ($p->list_price * 0.4));
                $lineSub = round($qty * $cost, 2);
                $lineTax = round($lineSub * 0.18, 2);
                $itemsData[] = [
                    'product_id' => $p->id, 'name' => $p->name,
                    'quantity_ordered' => $qty, 'quantity_received' => $plan['status'] === 'received' ? $qty : 0,
                    'unit_cost' => $cost, 'tax_pct' => 18,
                    'line_subtotal' => $lineSub, 'line_tax' => $lineTax,
                    'line_total' => round($lineSub + $lineTax, 2), 'sort_order' => $j,
                ];
                $subtotal += $lineSub;
            }

            $po = PurchaseOrder::create([
                'slug'        => Str::random(22),
                'prefix'      => 'PO',
                'reference'   => sprintf('PO-2026-%04d', $poCounter++),
                'supplier_company_id' => $supplier->id,
                'status'      => $plan['status'],
                'warehouse_id'=> $defaultWh->id,
                'order_date'  => Carbon::now()->subDays($plan['days_ago'])->toDateString(),
                'expected_delivery_date' => Carbon::now()->subDays($plan['days_ago'])->addDays(10)->toDateString(),
                'submitted_at'=> $plan['status'] !== 'draft' ? Carbon::now()->subDays($plan['days_ago'])->toDateTimeString() : null,
                'confirmed_at'=> in_array($plan['status'], ['confirmed','received']) ? Carbon::now()->subDays($plan['days_ago'] - 1)->toDateTimeString() : null,
                'currency_code' => $currency,
                'subtotal'    => $subtotal,
                'tax_total'   => round($subtotal * 0.18, 2),
                'grand_total' => round($subtotal * 1.18, 2),
                'payment_terms_days' => 30,
                'delivery_type' => 'courier',
                'owner_id'    => $admin->id,
                'tenant_id'   => $tenant->id,
                'created_by'  => $admin->id,
            ]);
            foreach ($itemsData as $it) {
                PurchaseOrderItem::create(array_merge($it, ['purchase_order_id' => $po->id]));
            }
        }
        $this->command?->info("  - {$tenant->name}: purchase orders OK.");
    }
}
