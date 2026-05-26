<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSalesSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('name', ['Empresa 1', 'Empresa 2'])->get();

        foreach ($tenants as $tenant) {
            if (Invoice::where('tenant_id', $tenant->id)->exists()) {
                $this->command?->warn("  · {$tenant->name}: ya tiene invoices, salteado.");
                continue;
            }

            $admin = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', 'not like', 'api+%')
                ->orderBy('id')
                ->first();

            if (!$admin) {
                $this->command?->warn("  · {$tenant->name}: sin admin, salteado.");
                continue;
            }

            DB::transaction(fn () => $this->seedForTenant($tenant, $admin));
            $this->command?->info("  · {$tenant->name}: demo sales (quotes/invoices/payments) creada.");
        }
    }

    protected function seedForTenant(Tenant $tenant, User $admin): void
    {
        $currency = $tenant->default_currency_code ?? 'USD';

        // ─── Payment methods ──────────────────────────────────────────────
        $methods = collect([
            ['name' => 'Transferencia bancaria', 'code' => 'transfer', 'requires_reference' => true],
            ['name' => 'Tarjeta de crédito',     'code' => 'card',     'requires_reference' => false],
            ['name' => 'Efectivo',               'code' => 'cash',     'requires_reference' => false],
            ['name' => 'Cheque',                 'code' => 'cheque',   'requires_reference' => true],
        ])->map(fn ($m) => PaymentMethod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $m['name']],
            [
                'slug'      => Str::random(22),
                'code'      => $m['code'],
                'requires_reference' => $m['requires_reference'],
                'is_active' => true,
                'sort_order'=> 0,
                'created_by'=> $admin->id,
            ]
        ));

        // ─── Datos base ─────────────────────────────────────────────────────
        $companies = Company::where('tenant_id', $tenant->id)->whereIn('company_type', ['customer', 'prospect'])->limit(6)->get();
        $products  = Product::where('tenant_id', $tenant->id)->where('is_active', true)->get();
        if ($companies->isEmpty() || $products->isEmpty()) {
            $this->command?->warn("  · {$tenant->name}: falta companies o products demo, salteado.");
            return;
        }

        // ─── QUOTES: 6 cotizaciones (mix draft/sent/accepted/rejected) ────
        $quotePlans = [
            ['status' => 'accepted', 'days_ago' => 30, 'valid' => 60,  'items' => 3],
            ['status' => 'sent',     'days_ago' => 5,  'valid' => 15,  'items' => 2],
            ['status' => 'draft',    'days_ago' => 0,  'valid' => 20,  'items' => 4],
            ['status' => 'accepted', 'days_ago' => 60, 'valid' => 60,  'items' => 2],
            ['status' => 'rejected', 'days_ago' => 20, 'valid' => 15,  'items' => 3],
            ['status' => 'sent',     'days_ago' => 2,  'valid' => 30,  'items' => 2],
        ];

        $now = Carbon::now();
        $quoteCounter = 1;
        $acceptedQuotes = collect();

        foreach ($quotePlans as $i => $plan) {
            $company = $companies->values()[$i % $companies->count()];
            $contact = Contact::where('company_id', $company->id)->first();
            $issueDate = $now->copy()->subDays($plan['days_ago']);

            // Crear lineas con productos reales
            $itemsData = [];
            $subtotal = 0;
            for ($j = 0; $j < $plan['items']; $j++) {
                $p = $products->random();
                $qty = rand(1, 5);
                $unit = (float) $p->list_price;
                $disc = $j === 0 ? 0 : (rand(0, 10) * 1.0);
                $lineSub = round($qty * $unit * (1 - $disc / 100), 2);
                $taxPct = 18.0; // IGV/IVA estándar
                $lineTax = round($lineSub * $taxPct / 100, 2);
                $lineTotal = round($lineSub + $lineTax, 2);
                $itemsData[] = [
                    'product_id' => $p->id,
                    'name'       => $p->name,
                    'description'=> $p->description,
                    'sku'        => $p->sku,
                    'quantity'   => $qty,
                    'unit_price' => $unit,
                    'discount_pct' => $disc,
                    'tax_pct'    => $taxPct,
                    'line_subtotal' => $lineSub,
                    'line_tax'   => $lineTax,
                    'line_total' => $lineTotal,
                    'sort_order' => $j,
                ];
                $subtotal += $lineSub;
            }
            $taxTotal = round($subtotal * 0.18, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);

            $quote = Quote::create([
                'tenant_id'      => $tenant->id,
                'created_by'     => $admin->id,
                'owner_id'       => $admin->id,
                'prefix'         => 'COT',
                'reference'      => sprintf('COT-2026-%04d', $quoteCounter++),
                'company_id'     => $company->id,
                'contact_id'     => $contact?->id,
                'status'         => $plan['status'],
                'issue_date'     => $issueDate->toDateString(),
                'valid_until'    => $issueDate->copy()->addDays($plan['valid'])->toDateString(),
                'sent_at'        => in_array($plan['status'], ['sent', 'accepted', 'rejected'])
                                    ? $issueDate->copy()->addHours(2)->toDateTimeString() : null,
                'accepted_at'    => $plan['status'] === 'accepted' ? $issueDate->copy()->addDays(3)->toDateTimeString() : null,
                'rejected_at'    => $plan['status'] === 'rejected' ? $issueDate->copy()->addDays(5)->toDateTimeString() : null,
                'rejected_reason'=> $plan['status'] === 'rejected' ? 'Postergaron decisión a Q3' : null,
                'currency_code'  => $company->preferred_currency_code ?? $currency,
                'subtotal'       => $subtotal,
                'tax_total'      => $taxTotal,
                'grand_total'    => $grandTotal,
                'discount_total' => 0,
                'shipping_cost'  => 0,
                'notes'          => 'Cotización válida según términos comerciales estándar.',
            ]);

            foreach ($itemsData as $it) {
                QuoteItem::create(array_merge($it, ['quote_id' => $quote->id]));
            }

            if ($plan['status'] === 'accepted') {
                $acceptedQuotes->push($quote);
            }
        }

        // ─── INVOICES: 4 facturas (las accepted quotes + 2 standalone) ────
        $invoiceCounter = 1;
        $createdInvoices = collect();

        // Convertir las accepted quotes a invoices
        foreach ($acceptedQuotes as $quote) {
            $issue = $quote->accepted_at ? Carbon::parse($quote->accepted_at) : $now->copy()->subDays(15);
            $invoice = Invoice::create([
                'tenant_id'     => $tenant->id,
                'created_by'    => $admin->id,
                'owner_id'      => $admin->id,
                'number'        => sprintf('FAC-2026-%05d', $invoiceCounter++),
                'prefix'        => 'FAC',
                'reference'     => sprintf('FAC-2026-%05d', $invoiceCounter),
                'document_type' => $currency === 'PEN' ? 'Factura' : 'A',
                'company_id'    => $quote->company_id,
                'contact_id'    => $quote->contact_id,
                'status'        => 'sent',
                'issue_date'    => $issue->toDateString(),
                'due_date'      => $issue->copy()->addDays(30)->toDateString(),
                'sent_at'       => $issue->copy()->addHours(1)->toDateTimeString(),
                'currency_code' => $quote->currency_code,
                'subtotal'      => $quote->subtotal,
                'tax_total'     => $quote->tax_total,
                'grand_total'   => $quote->grand_total,
                'amount_paid'   => 0,
                'balance_due'   => $quote->grand_total,
                'discount_total'=> 0,
                'shipping_cost' => 0,
                'billing_legal_name' => $quote->company->legal_name ?? $quote->company->name,
                'billing_tax_id'     => $quote->company->tax_id,
                'notes'         => 'Generada desde cotización ' . $quote->reference,
            ]);
            foreach ($quote->items as $item) {
                InvoiceItem::create([
                    'invoice_id'    => $invoice->id,
                    'product_id'    => $item->product_id,
                    'name'          => $item->name,
                    'description'   => $item->description,
                    'sku'           => $item->sku,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'discount_pct'  => $item->discount_pct,
                    'tax_pct'       => $item->tax_pct,
                    'line_subtotal' => $item->line_subtotal,
                    'line_tax'      => $item->line_tax,
                    'line_total'    => $item->line_total,
                    'sort_order'    => $item->sort_order,
                ]);
            }
            $createdInvoices->push($invoice);
        }

        // 2 standalone invoices con productos directos
        for ($k = 0; $k < 2; $k++) {
            $company = $companies->random();
            $items = $products->random(rand(1, 3));
            $subtotal = 0;
            $itemsData = [];
            foreach ($items as $j => $p) {
                $qty = rand(1, 3);
                $unit = (float) $p->list_price;
                $lineSub = round($qty * $unit, 2);
                $lineTax = round($lineSub * 0.18, 2);
                $itemsData[] = [
                    'product_id'    => $p->id,
                    'name'          => $p->name,
                    'sku'           => $p->sku,
                    'quantity'      => $qty,
                    'unit_price'    => $unit,
                    'discount_pct'  => 0,
                    'tax_pct'       => 18.0,
                    'line_subtotal' => $lineSub,
                    'line_tax'      => $lineTax,
                    'line_total'    => round($lineSub + $lineTax, 2),
                    'sort_order'    => $j,
                ];
                $subtotal += $lineSub;
            }
            $taxTotal = round($subtotal * 0.18, 2);
            $grandTotal = round($subtotal + $taxTotal, 2);
            $issue = $now->copy()->subDays(rand(10, 45));

            $invoice = Invoice::create([
                'tenant_id'     => $tenant->id,
                'created_by'    => $admin->id,
                'owner_id'      => $admin->id,
                'number'        => sprintf('FAC-2026-%05d', $invoiceCounter++),
                'prefix'        => 'FAC',
                'company_id'    => $company->id,
                'status'        => 'sent',
                'issue_date'    => $issue->toDateString(),
                'due_date'      => $issue->copy()->addDays(30)->toDateString(),
                'sent_at'       => $issue->copy()->addHours(1)->toDateTimeString(),
                'currency_code' => $company->preferred_currency_code ?? $currency,
                'subtotal'      => $subtotal,
                'tax_total'     => $taxTotal,
                'grand_total'   => $grandTotal,
                'amount_paid'   => 0,
                'balance_due'   => $grandTotal,
                'discount_total'=> 0,
                'shipping_cost' => 0,
            ]);
            foreach ($itemsData as $it) {
                InvoiceItem::create(array_merge($it, ['invoice_id' => $invoice->id]));
            }
            $createdInvoices->push($invoice);
        }

        // ─── PAYMENTS: pagar algunas invoices (full y partial) ────────────
        $paymentCounter = 1;
        $methodList = $methods->values();

        foreach ($createdInvoices as $idx => $invoice) {
            // Primera invoice: pago completo
            // Segunda: pago parcial 50%
            // Tercera: dos pagos que suman total
            // Resto: sin pago (queda pendiente)
            if ($idx === 0) {
                $this->createPayment($tenant, $admin, $invoice, $methodList, $paymentCounter++, (float)$invoice->grand_total, 'completed');
                $invoice->update(['amount_paid' => $invoice->grand_total, 'balance_due' => 0, 'status' => 'paid', 'paid_at' => $now]);
            } elseif ($idx === 1) {
                $half = round((float)$invoice->grand_total / 2, 2);
                $this->createPayment($tenant, $admin, $invoice, $methodList, $paymentCounter++, $half, 'completed');
                $invoice->update(['amount_paid' => $half, 'balance_due' => round((float)$invoice->grand_total - $half, 2), 'status' => 'partial']);
            } elseif ($idx === 2) {
                $half = round((float)$invoice->grand_total / 2, 2);
                $this->createPayment($tenant, $admin, $invoice, $methodList, $paymentCounter++, $half, 'completed');
                $this->createPayment($tenant, $admin, $invoice, $methodList, $paymentCounter++, round((float)$invoice->grand_total - $half, 2), 'completed');
                $invoice->update(['amount_paid' => $invoice->grand_total, 'balance_due' => 0, 'status' => 'paid', 'paid_at' => $now]);
            }
            // resto: pendiente
        }
    }

    protected function createPayment(Tenant $tenant, User $admin, Invoice $invoice, $methods, int $counter, float $amount, string $status): void
    {
        $method = $methods[$counter % count($methods)];
        Payment::create([
            'tenant_id'         => $tenant->id,
            'created_by'        => $admin->id,
            'reference'         => sprintf('PAGO-2026-%05d', $counter),
            'company_id'        => $invoice->company_id,
            'invoice_id'        => $invoice->id,
            'type'              => 'invoice_payment',
            'payment_method_id' => $method->id,
            'amount'            => $amount,
            'currency_code'     => $invoice->currency_code,
            'paid_at'           => Carbon::now()->subDays(rand(1, 20))->toDateTimeString(),
            'status'            => $status,
            'bank_reference'    => $method->requires_reference ? 'OP' . rand(100000, 999999) : null,
        ]);
    }
}
