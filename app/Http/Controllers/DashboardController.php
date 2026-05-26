<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $startMonth = $now->copy()->startOfMonth();
        $endMonth   = $now->copy()->endOfMonth();
        $prevMonthStart = $startMonth->copy()->subMonth();
        $prevMonthEnd   = $startMonth->copy()->subDay();

        // KPIs
        // Forecast: weighted_value sumado de deals open con cierre esperado
        // este mes o en los proximos 30 dias. weighted_value = value * (prob/100)
        // y se mantiene actualizado por el Deal model cada vez que cambia value
        // o probability_pct. Si weighted_value es null (data legacy), se cae
        // a value * 0.5 como fallback razonable.
        $deals_forecast_mtd = (float) Deal::where('status', 'open')
            ->whereBetween('expected_close_date', [$startMonth, $endMonth])
            ->sum(DB::raw('COALESCE(weighted_value, value * 0.5)'));
        $deals_forecast_next30d = (float) Deal::where('status', 'open')
            ->whereBetween('expected_close_date', [$now, $now->copy()->addDays(30)])
            ->sum(DB::raw('COALESCE(weighted_value, value * 0.5)'));

        $kpis = [
            'companies_total'     => Company::count(),
            'contacts_total'      => Contact::count(),
            'deals_open'          => Deal::where('status', 'open')->count(),
            'deals_won_value_mtd' => (float) Deal::where('status', 'won')
                ->whereBetween('won_at', [$startMonth, $endMonth])->sum('value'),
            'deals_pipeline_value'=> (float) Deal::where('status', 'open')->sum('value'),
            'deals_forecast_mtd'        => $deals_forecast_mtd,
            'deals_forecast_next30d'    => $deals_forecast_next30d,
            'quotes_sent'         => Quote::where('status', 'sent')->count(),
            'invoices_open'       => Invoice::whereIn('status', ['sent', 'partial', 'overdue'])->count(),
            'invoices_overdue'    => Invoice::where('status', 'sent')->whereDate('due_date', '<', $now->toDateString())->count(),
            'invoices_balance_due'=> (float) Invoice::whereIn('status', ['sent', 'partial', 'overdue'])->sum('balance_due'),
            'invoices_paid_mtd'   => (float) Invoice::where('status', 'paid')->whereBetween('paid_at', [$startMonth, $endMonth])->sum('grand_total'),
            'payments_count_mtd'  => Payment::whereBetween('paid_at', [$startMonth, $endMonth])->count(),
            'payments_total_mtd'  => (float) Payment::whereBetween('paid_at', [$startMonth, $endMonth])->sum('amount'),
            'sales_orders_pending'=> SalesOrder::whereIn('status', ['pending', 'processing'])->count(),
            'purchase_orders_open'=> PurchaseOrder::whereIn('status', ['submitted', 'confirmed', 'partially_received'])->count(),
        ];

        // Stock bajo (joinea con products para low_stock_threshold)
        $lowStock = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->select('p.name as product', 'p.sku', 'w.name as warehouse',
                'sl.qty_on_hand', 'sl.qty_reserved', 'p.low_stock_threshold')
            ->whereRaw('(sl.qty_on_hand - sl.qty_reserved) <= p.low_stock_threshold')
            ->where('p.low_stock_threshold', '>', 0)
            ->orderByRaw('(sl.qty_on_hand - sl.qty_reserved)')
            ->limit(10)
            ->get();

        // Recent deals won
        $recentWonDeals = Deal::where('status', 'won')
            ->with('company:id,name')
            ->orderBy('won_at', 'desc')->limit(5)->get(['id','slug','name','value','currency_code','won_at','company_id']);

        // Pipeline by stage (value per stage)
        $pipelineByStage = DB::table('deals as d')
            ->join('pipeline_stages as s', 's.id', '=', 'd.stage_id')
            ->where('d.status', 'open')
            ->groupBy('s.id', 's.name', 's.sort_order', 's.color')
            ->select('s.id', 's.name', 's.sort_order', 's.color', DB::raw('COUNT(*) as deal_count'), DB::raw('SUM(d.value) as total_value'))
            ->orderBy('s.sort_order')
            ->get();

        // Sales monthly trend (last 6 months — won deals)
        $salesTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $salesTrend[] = [
                'month' => $m->format('Y-m'),
                'label' => $m->locale('es')->isoFormat('MMM'),
                'value' => (float) Deal::where('status', 'won')
                    ->whereYear('won_at', $m->year)->whereMonth('won_at', $m->month)->sum('value'),
            ];
        }

        // Mi agenda — proximas 5 actividades pendientes del usuario actual.
        // Si no tiene perm view activities, queda vacio.
        $userId = $request->user()?->id;
        $myAgenda = $userId && $request->user()?->can('activities.view')
            ? \App\Models\Activity::query()
                ->where('actor_user_id', $userId)
                ->whereNull('completed_at')
                ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END, due_at ASC')
                ->limit(5)
                ->get(['id', 'slug', 'type', 'subject', 'body', 'due_at', 'priority', 'activitable_type', 'activitable_id'])
                ->map(function ($a) {
                    $parentLabel = null;
                    $parentUrl   = null;
                    if (class_exists($a->activitable_type)) {
                        $parent = $a->activitable_type::find($a->activitable_id);
                        if ($parent) {
                            $parentLabel = match ($a->activitable_type) {
                                \App\Models\Deal::class    => $parent->name,
                                \App\Models\Company::class => $parent->name,
                                \App\Models\Contact::class => trim(($parent->first_name ?? '') . ' ' . ($parent->last_name ?? '')),
                                default => null,
                            };
                            try {
                                $parentUrl = match ($a->activitable_type) {
                                    \App\Models\Deal::class    => route('crm.deals.show',     $parent->slug),
                                    \App\Models\Company::class => route('crm.companies.show', $parent->slug),
                                    \App\Models\Contact::class => route('crm.contacts.show',  $parent->slug),
                                    default => null,
                                };
                            } catch (\Throwable $e) { $parentUrl = null; }
                        }
                    }
                    return [
                        'id'           => $a->id,
                        'slug'         => $a->slug,
                        'type'         => $a->type,
                        'subject'      => $a->subject,
                        'body'         => $a->body ? mb_substr($a->body, 0, 80) : null,
                        'due_at'       => $a->due_at?->toIso8601String(),
                        'priority'     => $a->priority,
                        'is_overdue'   => $a->isOverdue(),
                        'parent_label' => $parentLabel,
                        'parent_url'   => $parentUrl,
                    ];
                })
                ->all()
            : [];

        return inertia('Dashboard/Business', [
            'kpis'            => $kpis,
            'lowStock'        => $lowStock,
            'recentWonDeals'  => $recentWonDeals,
            'pipelineByStage' => $pipelineByStage,
            'salesTrend'      => $salesTrend,
            'myAgenda'        => $myAgenda,
            'defaultCurrency' => $request->user()?->tenant?->default_currency_code ?? 'USD',
        ]);
    }
}
