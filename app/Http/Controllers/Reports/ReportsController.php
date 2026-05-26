<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Pipeline;
use App\Models\User;
use App\Models\LeadSource;
use App\Models\Currency;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ReportsController — agregaciones de negocio sobre Deals/Invoices/Activities/Stock.
 *
 * Todas las queries se ejecutan dentro del scope del tenant del user autenticado
 * (trait BelongsToTenant aplica el filtro global). Super ve el sistema entero
 * (bypass del scope).
 *
 * Cinco reportes:
 *   - salesPipeline : embudo Lead → Customer, valor por stage, tiempo promedio.
 *   - winRate       : win/loss por owner, lead source, stage; motivos perdida.
 *   - revenue       : ingresos mensuales 12m, top empresas, por categoria, YoY.
 *   - activity      : actividades por owner + tipo + vencidas.
 *   - operations    : facturas vencidas, stock bajo detallado, OCs abiertas.
 */
class ReportsController extends Controller
{
    /* =====================================================================
     * 1) SALES PIPELINE — embudo + valor por etapa + tiempo promedio
     * ===================================================================== */
    public function salesPipeline(Request $request)
    {
        $f = ReportFilters::fromRequest($request);

        // Embudo de lifecycle stages (vienen de contacts).
        $stages = ['lead', 'mql', 'sql', 'opportunity', 'customer'];
        $funnel = [];
        foreach ($stages as $stage) {
            $cq = Contact::where('lifecycle_stage', $stage);
            if ($f['date_from']) $cq->where('created_at', '>=', $f['date_from']);
            if ($f['date_to'])   $cq->where('created_at', '<=', $f['date_to']);
            if ($f['owner_id'])  $cq->where('owner_id', $f['owner_id']);
            $funnel[] = [
                'stage' => $stage,
                'count' => $cq->count(),
            ];
        }

        // Valor de deals abiertos por stage (joineamos pipeline_stages).
        // Importante: como Deal usa BelongsToTenant + super bypass, accedemos a
        // la tabla via query builder pero replicamos el scope manualmente.
        $tenantId   = $request->user()?->tenant_id;
        $isSuper    = $request->user()?->hasRole('super') ?? false;

        $valueByStageQ = DB::table('deals as d')
            ->join('pipeline_stages as s', 's.id', '=', 'd.stage_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'open')
            ->groupBy('s.id', 's.name', 's.sort_order', 's.color', 's.probability_pct')
            ->select(
                's.id',
                's.name',
                's.sort_order',
                's.color',
                's.probability_pct',
                DB::raw('COUNT(*) as deal_count'),
                DB::raw('COALESCE(SUM(d.value), 0) as total_value'),
                DB::raw('COALESCE(SUM(d.weighted_value), 0) as weighted_value')
            )
            ->orderBy('s.sort_order');
        if (! $isSuper && $tenantId) {
            $valueByStageQ->where('d.tenant_id', $tenantId);
        }
        if ($f['date_from']) $valueByStageQ->where('d.created_at', '>=', $f['date_from']);
        if ($f['date_to'])   $valueByStageQ->where('d.created_at', '<=', $f['date_to']);
        if ($f['pipeline_id']) $valueByStageQ->where('s.pipeline_id', $f['pipeline_id']);
        if ($f['owner_id'])    $valueByStageQ->where('d.owner_id', $f['owner_id']);
        $valueByStage = $valueByStageQ->get();

        // Tiempo promedio "time-in-stage" — usamos deal_stage_history.
        // Para cada stage destino, promediamos days_in_previous_stage del
        // movimiento que ABANDONA ese stage (i.e. from_stage_id = X).
        $avgTimeQ = DB::table('deal_stage_history as h')
            ->join('pipeline_stages as s', 's.id', '=', 'h.from_stage_id')
            ->join('deals as d', 'd.id', '=', 'h.deal_id')
            ->whereNotNull('h.from_stage_id')
            ->groupBy('s.id', 's.name', 's.sort_order', 's.color')
            ->select(
                's.id',
                's.name',
                's.sort_order',
                's.color',
                DB::raw('COUNT(*) as transitions'),
                DB::raw('ROUND(AVG(h.days_in_previous_stage)::numeric, 1) as avg_days')
            )
            ->orderBy('s.sort_order');
        if (! $isSuper && $tenantId) {
            $avgTimeQ->where('d.tenant_id', $tenantId);
        }
        // SQLite no soporta cast ::numeric — fallback simple.
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            $avgTimeQ = DB::table('deal_stage_history as h')
                ->join('pipeline_stages as s', 's.id', '=', 'h.from_stage_id')
                ->join('deals as d', 'd.id', '=', 'h.deal_id')
                ->whereNotNull('h.from_stage_id')
                ->groupBy('s.id', 's.name', 's.sort_order', 's.color')
                ->select(
                    's.id',
                    's.name',
                    's.sort_order',
                    's.color',
                    DB::raw('COUNT(*) as transitions'),
                    DB::raw('ROUND(AVG(h.days_in_previous_stage), 1) as avg_days')
                )
                ->orderBy('s.sort_order');
            if (! $isSuper && $tenantId) {
                $avgTimeQ->where('d.tenant_id', $tenantId);
            }
        }
        $avgTime = $avgTimeQ->get();

        return inertia('Reports/SalesPipeline', [
            'funnel'          => $funnel,
            'valueByStage'    => $valueByStage,
            'avgTimeByStage'  => $avgTime,
            'defaultCurrency' => $request->user()?->tenant?->default_currency_code ?? 'USD',
            'filters'         => $f,
            'options'         => [
                'pipelines' => $this->pipelineOptions(),
                'owners'    => $this->ownerOptions(),
            ],
        ]);
    }

    /* =====================================================================
     * 2) WIN RATE — analisis ganancia/perdida
     * ===================================================================== */
    public function winRate(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;
        $f = ReportFilters::fromRequest($request);

        // Filtros aplicados al cierre (won_at / lost_at). Para Builder en
        // raw queries, expresamos el closed_at como COALESCE(won_at, lost_at).
        $applyDealFilters = function ($q, $alias = 'd') use ($f) {
            if ($f['date_from']) $q->where(DB::raw("COALESCE({$alias}.won_at, {$alias}.lost_at)"), '>=', $f['date_from']);
            if ($f['date_to'])   $q->where(DB::raw("COALESCE({$alias}.won_at, {$alias}.lost_at)"), '<=', $f['date_to']);
            if ($f['owner_id'])       $q->where("{$alias}.owner_id", $f['owner_id']);
            if ($f['lead_source_id']) $q->where("{$alias}.lead_source_id", $f['lead_source_id']);
            if ($f['pipeline_id']) {
                $q->whereExists(function ($sub) use ($f, $alias) {
                    $sub->select(DB::raw(1))->from('pipeline_stages as ps')
                        ->whereColumn('ps.id', "{$alias}.stage_id")
                        ->where('ps.pipeline_id', $f['pipeline_id']);
                });
            }
        };

        // Win rate por owner: deals_won / (deals_won + deals_lost).
        $byOwnerQ = DB::table('deals as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.owner_id')
            ->whereNull('d.deleted_at')
            ->whereIn('d.status', ['won', 'lost'])
            ->groupBy('d.owner_id', 'u.name', 'u.email')
            ->select(
                'd.owner_id',
                'u.name as owner_name',
                'u.email as owner_email',
                DB::raw("SUM(CASE WHEN d.status='won' THEN 1 ELSE 0 END) as won_count"),
                DB::raw("SUM(CASE WHEN d.status='lost' THEN 1 ELSE 0 END) as lost_count"),
                DB::raw("COALESCE(SUM(CASE WHEN d.status='won' THEN d.value ELSE 0 END), 0) as won_value"),
                DB::raw("COALESCE(SUM(CASE WHEN d.status='lost' THEN d.value ELSE 0 END), 0) as lost_value")
            );
        if (! $isSuper && $tenantId) {
            $byOwnerQ->where('d.tenant_id', $tenantId);
        }
        $applyDealFilters($byOwnerQ);
        $byOwner = $byOwnerQ->get()->map(function ($r) {
            $total = (int) $r->won_count + (int) $r->lost_count;
            $r->win_rate = $total > 0 ? round(((int) $r->won_count / $total) * 100, 1) : 0.0;
            return $r;
        })->sortByDesc('win_rate')->values();

        // Win rate por lead source.
        $bySourceQ = DB::table('deals as d')
            ->leftJoin('lead_sources as ls', 'ls.id', '=', 'd.lead_source_id')
            ->whereNull('d.deleted_at')
            ->whereIn('d.status', ['won', 'lost'])
            ->groupBy('d.lead_source_id', 'ls.name')
            ->select(
                'd.lead_source_id',
                'ls.name as source_name',
                DB::raw("SUM(CASE WHEN d.status='won' THEN 1 ELSE 0 END) as won_count"),
                DB::raw("SUM(CASE WHEN d.status='lost' THEN 1 ELSE 0 END) as lost_count")
            );
        if (! $isSuper && $tenantId) {
            $bySourceQ->where('d.tenant_id', $tenantId);
        }
        $applyDealFilters($bySourceQ);
        $bySource = $bySourceQ->get()->map(function ($r) {
            $total = (int) $r->won_count + (int) $r->lost_count;
            $r->win_rate = $total > 0 ? round(((int) $r->won_count / $total) * 100, 1) : 0.0;
            $r->source_name = $r->source_name ?? '—';
            return $r;
        })->sortByDesc('win_rate')->values();

        // Donde se pierden los deals — distribucion de status lost por stage.
        $byStageQ = DB::table('deals as d')
            ->join('pipeline_stages as s', 's.id', '=', 'd.stage_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'lost')
            ->groupBy('s.id', 's.name', 's.color', 's.sort_order')
            ->select(
                's.id',
                's.name as stage_name',
                's.color',
                's.sort_order',
                DB::raw('COUNT(*) as lost_count'),
                DB::raw('COALESCE(SUM(d.value), 0) as lost_value')
            )
            ->orderBy('s.sort_order');
        if (! $isSuper && $tenantId) {
            $byStageQ->where('d.tenant_id', $tenantId);
        }
        $applyDealFilters($byStageQ);
        $byStage = $byStageQ->get();

        // Top motivos de perdida — agrupado por lost_reason_note (texto libre).
        // No tenemos taxonomia estructurada, asi que groupBy del texto directo.
        $reasonsQ = DB::table('deals')
            ->whereNull('deleted_at')
            ->where('status', 'lost')
            ->whereNotNull('lost_reason_note')
            ->where('lost_reason_note', '!=', '')
            ->groupBy('lost_reason_note')
            ->select(
                'lost_reason_note as reason',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(value), 0) as lost_value')
            )
            ->orderByDesc('cnt')
            ->limit(5);
        if (! $isSuper && $tenantId) {
            $reasonsQ->where('tenant_id', $tenantId);
        }
        // El query de reasons usa la tabla deals sin alias — adaptamos el closure.
        if ($f['date_from']) $reasonsQ->where(DB::raw('COALESCE(won_at, lost_at)'), '>=', $f['date_from']);
        if ($f['date_to'])   $reasonsQ->where(DB::raw('COALESCE(won_at, lost_at)'), '<=', $f['date_to']);
        if ($f['owner_id'])       $reasonsQ->where('owner_id', $f['owner_id']);
        if ($f['lead_source_id']) $reasonsQ->where('lead_source_id', $f['lead_source_id']);
        $reasons = $reasonsQ->get();

        return inertia('Reports/WinRate', [
            'byOwner'         => $byOwner,
            'bySource'        => $bySource,
            'byStage'         => $byStage,
            'topLostReasons'  => $reasons,
            'defaultCurrency' => $request->user()?->tenant?->default_currency_code ?? 'USD',
            'filters'         => $f,
            'options'         => [
                'pipelines'   => $this->pipelineOptions(),
                'owners'      => $this->ownerOptions(),
                'leadSources' => $this->leadSourceOptions(),
            ],
        ]);
    }

    /* =====================================================================
     * 3) REVENUE — ingresos mensuales + top empresas + por categoria + YoY
     * ===================================================================== */
    public function revenue(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;
        $now      = Carbon::now();
        $f = ReportFilters::fromRequest($request);
        $currencyFilter = $f['currency_code'];

        // Ingresos mensuales: ultimos 12 meses (paid invoices por paid_at).
        $monthly = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $startM = $m->copy()->startOfMonth();
            $endM   = $m->copy()->endOfMonth();

            $q = Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [$startM, $endM]);
            if ($currencyFilter) $q->where('currency_code', $currencyFilter);
            $value = (float) $q->sum('grand_total');

            // YoY: mismo mes año anterior.
            $startPrev = $m->copy()->subYear()->startOfMonth();
            $endPrev   = $m->copy()->subYear()->endOfMonth();
            $qPrev = Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [$startPrev, $endPrev]);
            if ($currencyFilter) $qPrev->where('currency_code', $currencyFilter);
            $valuePrev = (float) $qPrev->sum('grand_total');

            $monthly[] = [
                'month'      => $m->format('Y-m'),
                'label'      => $m->locale(app()->getLocale())->isoFormat('MMM YY'),
                'value'      => $value,
                'value_prev' => $valuePrev,
                'yoy_pct'    => $valuePrev > 0
                    ? round((($value - $valuePrev) / $valuePrev) * 100, 1)
                    : null,
            ];
        }

        // Top 10 empresas por revenue total (paid invoices).
        $byCompanyQ = DB::table('invoices as i')
            ->join('companies as c', 'c.id', '=', 'i.company_id')
            ->whereNull('i.deleted_at')
            ->where('i.status', 'paid')
            ->groupBy('c.id', 'c.name')
            ->select(
                'c.id',
                'c.name as company_name',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(i.grand_total), 0) as total_revenue')
            )
            ->orderByDesc('total_revenue')
            ->limit(10);
        if (! $isSuper && $tenantId) {
            $byCompanyQ->where('i.tenant_id', $tenantId);
        }
        if ($f['date_from']) $byCompanyQ->where('i.paid_at', '>=', $f['date_from']);
        if ($f['date_to'])   $byCompanyQ->where('i.paid_at', '<=', $f['date_to']);
        if ($currencyFilter) $byCompanyQ->where('i.currency_code', $currencyFilter);
        $byCompany = $byCompanyQ->get();

        // Revenue por categoria de producto (joineando invoice_items → products).
        $byCategoryQ = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->whereNull('i.deleted_at')
            ->where('i.status', 'paid')
            ->groupBy('pc.id', 'pc.name')
            ->select(
                'pc.id as category_id',
                DB::raw("COALESCE(pc.name, '—') as category_name"),
                DB::raw('COUNT(DISTINCT ii.invoice_id) as invoice_count'),
                DB::raw('COALESCE(SUM(ii.line_total), 0) as total_revenue')
            )
            ->orderByDesc('total_revenue')
            ->limit(15);
        if (! $isSuper && $tenantId) {
            $byCategoryQ->where('i.tenant_id', $tenantId);
        }
        if ($f['date_from']) $byCategoryQ->where('i.paid_at', '>=', $f['date_from']);
        if ($f['date_to'])   $byCategoryQ->where('i.paid_at', '<=', $f['date_to']);
        if ($currencyFilter) $byCategoryQ->where('i.currency_code', $currencyFilter);
        $byCategory = $byCategoryQ->get();

        return inertia('Reports/Revenue', [
            'monthly'         => $monthly,
            'byCompany'       => $byCompany,
            'byCategory'      => $byCategory,
            'defaultCurrency' => $request->user()?->tenant?->default_currency_code ?? 'USD',
            'filters'         => $f,
            'options'         => [
                'currencies' => $this->currencyOptions(),
            ],
        ]);
    }

    /* =====================================================================
     * 4) ACTIVITY — productividad de sales reps
     * ===================================================================== */
    public function activity(Request $request)
    {
        $now        = Carbon::now();
        $startWeek  = $now->copy()->startOfWeek();
        $startMonth = $now->copy()->startOfMonth();
        $f = ReportFilters::fromRequest($request);
        // Format explícito a 'Y-m-d H:i:s' para que el driver no se quiebre
        // con Carbon objects directos en selectRaw bindings (algunos drivers
        // los convierten correctamente, otros no).
        $startWeekStr  = $startWeek->toDateTimeString();
        $startMonthStr = $startMonth->toDateTimeString();
        $nowStr        = $now->toDateTimeString();

        // Activities count por owner (esta semana + este mes).
        // Usamos actor_user_id (quien registro la activity).
        $byOwnerQ = Activity::query()
            ->selectRaw('actor_user_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as count_this_week', [$startWeekStr])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as count_this_month', [$startMonthStr])
            ->selectRaw('SUM(CASE WHEN due_at < ? AND completed_at IS NULL THEN 1 ELSE 0 END) as overdue_count', [$nowStr])
            ->whereNotNull('actor_user_id')
            ->groupBy('actor_user_id')
            ->orderByDesc('total')
            ->limit(50);
        if ($f['date_from']) $byOwnerQ->where('created_at', '>=', $f['date_from']);
        if ($f['date_to'])   $byOwnerQ->where('created_at', '<=', $f['date_to']);
        if ($f['owner_id'])     $byOwnerQ->where('actor_user_id', $f['owner_id']);
        if ($f['activity_type']) $byOwnerQ->where('type', $f['activity_type']);
        $byOwner = $byOwnerQ->get();

        // Nombre del owner (cargado aparte para evitar global scope de users).
        $userIds = $byOwner->pluck('actor_user_id')->all();
        $userMap = \App\Models\User::withoutGlobalScopes()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $byOwner = $byOwner->map(function ($r) use ($userMap) {
            $u = $userMap->get($r->actor_user_id);
            return [
                'actor_user_id'   => $r->actor_user_id,
                'owner_name'      => $u?->name ?? '—',
                'owner_email'     => $u?->email ?? '—',
                'total'           => (int) $r->total,
                'count_this_week' => (int) $r->count_this_week,
                'count_this_month'=> (int) $r->count_this_month,
                'overdue_count'   => (int) $r->overdue_count,
            ];
        });

        // Matriz: activities por owner × type (call/email/meeting/task/note).
        $byOwnerType = Activity::query()
            ->selectRaw('actor_user_id, type, COUNT(*) as cnt')
            ->whereNotNull('actor_user_id')
            ->where('created_at', '>=', $startMonth)
            ->groupBy('actor_user_id', 'type')
            ->get();

        // Reshape como [user_id => {call:N, email:N, ...}]
        $matrix = [];
        foreach ($byOwnerType as $row) {
            $matrix[$row->actor_user_id][$row->type] = (int) $row->cnt;
        }
        $matrixOut = [];
        foreach ($userIds as $uid) {
            $u = $userMap->get($uid);
            $matrixOut[] = [
                'actor_user_id' => $uid,
                'owner_name'    => $u?->name ?? '—',
                'call'    => $matrix[$uid]['call']    ?? 0,
                'email'   => $matrix[$uid]['email']   ?? 0,
                'meeting' => $matrix[$uid]['meeting'] ?? 0,
                'task'    => $matrix[$uid]['task']    ?? 0,
                'note'    => $matrix[$uid]['note']    ?? 0,
            ];
        }

        // Vencidas (pending due_at < now) — lista detallada top 20.
        $overdue = Activity::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->with('actor:id,name,email')
            ->orderBy('due_at')
            ->limit(20)
            ->get(['id', 'slug', 'type', 'subject', 'due_at', 'priority', 'actor_user_id'])
            ->map(fn ($a) => [
                'id'         => $a->id,
                'slug'       => $a->slug,
                'type'       => $a->type,
                'subject'    => $a->subject,
                'due_at'     => $a->due_at?->toIso8601String(),
                'priority'   => $a->priority,
                'owner_name' => $a->actor?->name ?? '—',
                'days_late'  => $a->due_at ? (int) $a->due_at->diffInDays($now) : 0,
            ]);

        return inertia('Reports/Activity', [
            'byOwner'     => $byOwner,
            'byOwnerType' => $matrixOut,
            'overdue'     => $overdue,
            'filters'     => $f,
            'options'     => [
                'owners'        => $this->ownerOptions(),
                'activityTypes' => $this->activityTypeOptions(),
            ],
        ]);
    }

    /* =====================================================================
     * 5) OPERATIONS — health checks de invoices, stock y purchase orders
     * ===================================================================== */
    public function operations(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;
        $today    = Carbon::now()->toDateString();
        $f = ReportFilters::fromRequest($request, 'all');
        $currencyFilter = $f['currency_code'];

        // Facturas vencidas (due_date < hoy AND balance_due > 0) — por empresa.
        $overdueInvoicesQ = DB::table('invoices as i')
            ->join('companies as c', 'c.id', '=', 'i.company_id')
            ->whereNull('i.deleted_at')
            ->whereIn('i.status', ['sent', 'partial', 'overdue'])
            ->where('i.due_date', '<', $today)
            ->where('i.balance_due', '>', 0)
            ->groupBy('c.id', 'c.name')
            ->select(
                'c.id',
                'c.name as company_name',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(i.balance_due), 0) as total_overdue')
            )
            ->orderByDesc('total_overdue')
            ->limit(20);
        if (! $isSuper && $tenantId) {
            $overdueInvoicesQ->where('i.tenant_id', $tenantId);
        }
        if ($currencyFilter) $overdueInvoicesQ->where('i.currency_code', $currencyFilter);
        $overdueByCompany = $overdueInvoicesQ->get();

        // Total vencido (sumatoria).
        $overdueTotalsQ = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('due_date', '<', $today)
            ->where('balance_due', '>', 0)
            ->select(
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(balance_due), 0) as total')
            );
        if (! $isSuper && $tenantId) {
            $overdueTotalsQ->where('tenant_id', $tenantId);
        }
        if ($currencyFilter) $overdueTotalsQ->where('currency_code', $currencyFilter);
        $overdueTotals = $overdueTotalsQ->first();

        // Stock bajo por warehouse + categoria.
        $lowStockByWarehouseQ = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->whereNull('p.deleted_at')
            ->where('p.low_stock_threshold', '>', 0)
            ->whereRaw('(sl.qty_on_hand - sl.qty_reserved) <= p.low_stock_threshold')
            ->groupBy('w.id', 'w.name')
            ->select(
                'w.id',
                'w.name as warehouse_name',
                DB::raw('COUNT(DISTINCT p.id) as product_count')
            )
            ->orderByDesc('product_count');
        if (! $isSuper && $tenantId) {
            $lowStockByWarehouseQ->where('sl.tenant_id', $tenantId);
        }
        $lowStockByWarehouse = $lowStockByWarehouseQ->get();

        $lowStockByCategoryQ = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->whereNull('p.deleted_at')
            ->where('p.low_stock_threshold', '>', 0)
            ->whereRaw('(sl.qty_on_hand - sl.qty_reserved) <= p.low_stock_threshold')
            ->groupBy('pc.id', 'pc.name')
            ->select(
                'pc.id',
                DB::raw("COALESCE(pc.name, '—') as category_name"),
                DB::raw('COUNT(DISTINCT p.id) as product_count')
            )
            ->orderByDesc('product_count');
        if (! $isSuper && $tenantId) {
            $lowStockByCategoryQ->where('sl.tenant_id', $tenantId);
        }
        $lowStockByCategory = $lowStockByCategoryQ->get();

        // Detalle de productos con stock bajo (top 20).
        $lowStockDetailQ = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->whereNull('p.deleted_at')
            ->where('p.low_stock_threshold', '>', 0)
            ->whereRaw('(sl.qty_on_hand - sl.qty_reserved) <= p.low_stock_threshold')
            ->select(
                'p.id',
                'p.sku',
                'p.name as product_name',
                'w.name as warehouse_name',
                'sl.qty_on_hand',
                'sl.qty_reserved',
                'p.low_stock_threshold'
            )
            ->orderByRaw('(sl.qty_on_hand - sl.qty_reserved) ASC')
            ->limit(20);
        if (! $isSuper && $tenantId) {
            $lowStockDetailQ->where('sl.tenant_id', $tenantId);
        }
        $lowStockDetail = $lowStockDetailQ->get();

        // OCs abiertas (submitted/confirmed) — por proveedor.
        $pendingPOsQ = DB::table('purchase_orders as po')
            ->join('companies as c', 'c.id', '=', 'po.supplier_company_id')
            ->whereNull('po.deleted_at')
            ->whereIn('po.status', ['submitted', 'confirmed'])
            ->groupBy('c.id', 'c.name')
            ->select(
                'c.id',
                'c.name as supplier_name',
                DB::raw('COUNT(*) as po_count'),
                DB::raw('COALESCE(SUM(po.grand_total), 0) as total_value')
            )
            ->orderByDesc('total_value')
            ->limit(20);
        if (! $isSuper && $tenantId) {
            $pendingPOsQ->where('po.tenant_id', $tenantId);
        }
        $pendingPOsBySupplier = $pendingPOsQ->get();

        $pendingPOsTotalsQ = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereIn('status', ['submitted', 'confirmed'])
            ->select(
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(grand_total), 0) as total')
            );
        if (! $isSuper && $tenantId) {
            $pendingPOsTotalsQ->where('tenant_id', $tenantId);
        }
        $pendingPOsTotals = $pendingPOsTotalsQ->first();

        return inertia('Reports/Operations', [
            'overdueByCompany'      => $overdueByCompany,
            'overdueTotals'         => [
                'count' => (int) ($overdueTotals->cnt ?? 0),
                'total' => (float) ($overdueTotals->total ?? 0),
            ],
            'lowStockByWarehouse'   => $lowStockByWarehouse,
            'lowStockByCategory'    => $lowStockByCategory,
            'lowStockDetail'        => $lowStockDetail,
            'pendingPOsBySupplier'  => $pendingPOsBySupplier,
            'pendingPOsTotals'      => [
                'count' => (int) ($pendingPOsTotals->cnt ?? 0),
                'total' => (float) ($pendingPOsTotals->total ?? 0),
            ],
            'defaultCurrency'       => $request->user()?->tenant?->default_currency_code ?? 'USD',
            'filters'               => $f,
            'options'               => [
                'currencies' => $this->currencyOptions(),
            ],
        ]);
    }

    /* =====================================================================
     * Helpers reusables para llenar los dropdowns del filter bar.
     * Cada uno respeta el scope multi-tenant (Pipeline/User/LeadSource usan
     * BelongsToTenant) salvo super que ve global.
     * ===================================================================== */
    protected function pipelineOptions(): array
    {
        return Pipeline::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    protected function ownerOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    protected function leadSourceOptions(): array
    {
        return LeadSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    protected function currencyOptions(): array
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code'])
            ->toArray();
    }

    protected function activityTypeOptions(): array
    {
        return [
            ['value' => 'call',    'label' => 'Llamada'],
            ['value' => 'email',   'label' => 'Email'],
            ['value' => 'meeting', 'label' => 'Reunión'],
            ['value' => 'task',    'label' => 'Tarea'],
            ['value' => 'note',    'label' => 'Nota'],
        ];
    }

    /* =====================================================================
     * EXPORTS — PDF + Excel del reporte ya filtrado.
     * Reutilizan los mismos metodos publicos del reporte para obtener data,
     * la convierten al formato { sections: [{ title, columns, rows }] } y
     * la pasan al template Blade o al Export de Maatwebsite.
     * ===================================================================== */
    public function exportPdf(Request $request, string $report)
    {
        $built = $this->buildExportPayload($request, $report);
        $tenant = $request->user()?->tenant;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
            'title'           => $built['title'],
            'tenant'          => $tenant,
            'sections'        => $built['sections'],
            'filtersSummary'  => $built['filtersSummary'],
        ])->setPaper('a4', 'landscape');
        return $pdf->stream("{$report}.pdf");
    }

    public function exportExcel(Request $request, string $report)
    {
        $built = $this->buildExportPayload($request, $report);
        $filename = "{$report}.xlsx";
        return (new \App\Exports\Reports\ReportSectionsExport($built['sections'], $built['filtersSummary']))
            ->download($filename);
    }

    /**
     * Builder por reporte — para cada uno arma el payload de export
     * (title, sections, filtersSummary) reutilizando los queries del
     * reporte. Cada bloque sigue el patron del metodo de Inertia respectivo
     * (filtros del request → queries → data) pero formatea las secciones
     * de forma generica para el PDF / Excel.
     */
    protected function buildExportPayload(Request $request, string $report): array
    {
        $f = ReportFilters::fromRequest($request, $report === 'operations' ? 'all' : 'last_30d');
        $filtersSummary = $this->describeFilters($f);

        return match ($report) {
            'sales_pipeline' => $this->exportPayloadSalesPipeline($request, $f, $filtersSummary),
            'win_rate'       => $this->exportPayloadWinRate($request, $f, $filtersSummary),
            'revenue'        => $this->exportPayloadRevenue($request, $f, $filtersSummary),
            'activity'       => $this->exportPayloadActivity($request, $f, $filtersSummary),
            'operations'     => $this->exportPayloadOperations($request, $f, $filtersSummary),
            default => abort(404, "Unknown report: {$report}"),
        };
    }

    protected function describeFilters(array $f): string
    {
        $parts = [];
        if ($f['date_from'] && $f['date_to']) {
            $parts[] = "Fechas: " . $f['date_from']->format('d/m/Y') . " — " . $f['date_to']->format('d/m/Y');
        }
        if ($f['pipeline_id'])    $parts[] = "Pipeline #{$f['pipeline_id']}";
        if ($f['owner_id'])       $parts[] = "Vendedor #{$f['owner_id']}";
        if ($f['lead_source_id']) $parts[] = "Origen #{$f['lead_source_id']}";
        if ($f['currency_code'])  $parts[] = "Moneda: {$f['currency_code']}";
        if ($f['activity_type'])  $parts[] = "Tipo: {$f['activity_type']}";
        return implode(' · ', $parts);
    }

    protected function exportPayloadSalesPipeline(Request $request, array $f, string $fs): array
    {
        // Reutilizamos el response del salesPipeline llamando a la accion y
        // tomando los props. Mas robusto: replicamos los queries (sin duplicar
        // el filter logic — ya esta encapsulado en applyDealFiltersByCreatedAt).
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;

        $valueByStageQ = DB::table('deals as d')
            ->join('pipeline_stages as s', 's.id', '=', 'd.stage_id')
            ->whereNull('d.deleted_at')->where('d.status', 'open')
            ->groupBy('s.id', 's.name', 's.sort_order', 's.color', 's.probability_pct')
            ->select('s.name', 's.probability_pct',
                DB::raw('COUNT(*) as deal_count'),
                DB::raw('COALESCE(SUM(d.value), 0) as total_value'),
                DB::raw('COALESCE(SUM(d.weighted_value), 0) as weighted_value'))
            ->orderBy('s.sort_order');
        if (!$isSuper && $tenantId) $valueByStageQ->where('d.tenant_id', $tenantId);
        if ($f['date_from']) $valueByStageQ->where('d.created_at', '>=', $f['date_from']);
        if ($f['date_to'])   $valueByStageQ->where('d.created_at', '<=', $f['date_to']);
        if ($f['pipeline_id']) $valueByStageQ->where('s.pipeline_id', $f['pipeline_id']);
        if ($f['owner_id'])    $valueByStageQ->where('d.owner_id', $f['owner_id']);

        return [
            'title' => 'Reporte: Sales Pipeline',
            'filtersSummary' => $fs,
            'sections' => [[
                'title' => 'Deals abiertos por etapa',
                'columns' => [
                    ['key' => 'name',           'title' => 'Etapa',         'align' => 'left'],
                    ['key' => 'deal_count',     'title' => '# Deals',       'align' => 'right'],
                    ['key' => 'total_value',    'title' => 'Valor total',   'align' => 'right', 'type' => 'money'],
                    ['key' => 'weighted_value', 'title' => 'Valor ponderado','align' => 'right', 'type' => 'money'],
                    ['key' => 'probability_pct','title' => 'Probabilidad',  'align' => 'right', 'type' => 'pct'],
                ],
                'rows' => $valueByStageQ->get(),
            ]],
        ];
    }

    protected function exportPayloadWinRate(Request $request, array $f, string $fs): array
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;

        $apply = function ($q, $alias = 'd') use ($f) {
            if ($f['date_from']) $q->where(DB::raw("COALESCE({$alias}.won_at, {$alias}.lost_at)"), '>=', $f['date_from']);
            if ($f['date_to'])   $q->where(DB::raw("COALESCE({$alias}.won_at, {$alias}.lost_at)"), '<=', $f['date_to']);
            if ($f['owner_id'])       $q->where("{$alias}.owner_id", $f['owner_id']);
            if ($f['lead_source_id']) $q->where("{$alias}.lead_source_id", $f['lead_source_id']);
            if ($f['pipeline_id']) {
                $q->whereExists(function ($sub) use ($f, $alias) {
                    $sub->select(DB::raw(1))->from('pipeline_stages as ps')
                        ->whereColumn('ps.id', "{$alias}.stage_id")
                        ->where('ps.pipeline_id', $f['pipeline_id']);
                });
            }
        };

        $byOwnerQ = DB::table('deals as d')->leftJoin('users as u', 'u.id', '=', 'd.owner_id')
            ->whereNull('d.deleted_at')->whereIn('d.status', ['won', 'lost'])
            ->groupBy('d.owner_id', 'u.name')
            ->select(DB::raw('COALESCE(u.name, \'—\') as owner_name'),
                DB::raw("SUM(CASE WHEN d.status='won' THEN 1 ELSE 0 END) as won_count"),
                DB::raw("SUM(CASE WHEN d.status='lost' THEN 1 ELSE 0 END) as lost_count"),
                DB::raw("COALESCE(SUM(CASE WHEN d.status='won' THEN d.value ELSE 0 END), 0) as won_value"));
        if (!$isSuper && $tenantId) $byOwnerQ->where('d.tenant_id', $tenantId);
        $apply($byOwnerQ);
        $byOwner = $byOwnerQ->get()->map(function ($r) {
            $total = (int) $r->won_count + (int) $r->lost_count;
            $r->win_rate = $total > 0 ? round(((int) $r->won_count / $total) * 100, 1) : 0.0;
            return $r;
        });

        return [
            'title' => 'Reporte: Win Rate',
            'filtersSummary' => $fs,
            'sections' => [[
                'title' => 'Tasa de cierre por vendedor',
                'columns' => [
                    ['key' => 'owner_name', 'title' => 'Vendedor',  'align' => 'left'],
                    ['key' => 'won_count',  'title' => 'Ganados',   'align' => 'right'],
                    ['key' => 'lost_count', 'title' => 'Perdidos',  'align' => 'right'],
                    ['key' => 'won_value',  'title' => 'Valor ganado', 'align' => 'right', 'type' => 'money'],
                    ['key' => 'win_rate',   'title' => 'Win rate',   'align' => 'right', 'type' => 'pct'],
                ],
                'rows' => $byOwner,
            ]],
        ];
    }

    protected function exportPayloadRevenue(Request $request, array $f, string $fs): array
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;

        $byCompanyQ = DB::table('invoices as i')->join('companies as c', 'c.id', '=', 'i.company_id')
            ->whereNull('i.deleted_at')->where('i.status', 'paid')
            ->groupBy('c.id', 'c.name')
            ->select('c.name as company_name',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(i.grand_total), 0) as total_revenue'))
            ->orderByDesc('total_revenue')->limit(20);
        if (!$isSuper && $tenantId) $byCompanyQ->where('i.tenant_id', $tenantId);
        if ($f['date_from']) $byCompanyQ->where('i.paid_at', '>=', $f['date_from']);
        if ($f['date_to'])   $byCompanyQ->where('i.paid_at', '<=', $f['date_to']);
        if ($f['currency_code']) $byCompanyQ->where('i.currency_code', $f['currency_code']);

        return [
            'title' => 'Reporte: Revenue',
            'filtersSummary' => $fs,
            'sections' => [[
                'title' => 'Top empresas por facturación pagada',
                'columns' => [
                    ['key' => 'company_name', 'title' => 'Empresa',  'align' => 'left'],
                    ['key' => 'invoice_count','title' => '# Facturas','align' => 'right'],
                    ['key' => 'total_revenue','title' => 'Total cobrado', 'align' => 'right', 'type' => 'money'],
                ],
                'rows' => $byCompanyQ->get(),
            ]],
        ];
    }

    protected function exportPayloadActivity(Request $request, array $f, string $fs): array
    {
        $byOwnerQ = Activity::query()
            ->selectRaw('actor_user_id')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('actor_user_id')
            ->groupBy('actor_user_id')
            ->orderByDesc('total')->limit(50);
        if ($f['date_from']) $byOwnerQ->where('created_at', '>=', $f['date_from']);
        if ($f['date_to'])   $byOwnerQ->where('created_at', '<=', $f['date_to']);
        if ($f['owner_id'])      $byOwnerQ->where('actor_user_id', $f['owner_id']);
        if ($f['activity_type']) $byOwnerQ->where('type', $f['activity_type']);
        $rows = $byOwnerQ->get();
        $uMap = User::withoutGlobalScopes()->whereIn('id', $rows->pluck('actor_user_id'))->get(['id', 'name'])->keyBy('id');
        $rows = $rows->map(fn ($r) => [
            'owner_name' => $uMap->get($r->actor_user_id)?->name ?? '—',
            'total'      => (int) $r->total,
        ]);

        return [
            'title' => 'Reporte: Actividad',
            'filtersSummary' => $fs,
            'sections' => [[
                'title' => 'Actividades por vendedor',
                'columns' => [
                    ['key' => 'owner_name', 'title' => 'Vendedor',         'align' => 'left'],
                    ['key' => 'total',      'title' => 'Total actividades', 'align' => 'right'],
                ],
                'rows' => $rows,
            ]],
        ];
    }

    protected function exportPayloadOperations(Request $request, array $f, string $fs): array
    {
        $tenantId = $request->user()?->tenant_id;
        $isSuper  = $request->user()?->hasRole('super') ?? false;
        $today    = Carbon::now()->toDateString();

        $overdueQ = DB::table('invoices as i')->join('companies as c', 'c.id', '=', 'i.company_id')
            ->whereNull('i.deleted_at')->whereIn('i.status', ['sent', 'partial', 'overdue'])
            ->where('i.due_date', '<', $today)->where('i.balance_due', '>', 0)
            ->groupBy('c.id', 'c.name')
            ->select('c.name as company_name',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(i.balance_due), 0) as total_overdue'))
            ->orderByDesc('total_overdue')->limit(50);
        if (!$isSuper && $tenantId) $overdueQ->where('i.tenant_id', $tenantId);
        if ($f['currency_code']) $overdueQ->where('i.currency_code', $f['currency_code']);

        return [
            'title' => 'Reporte: Operaciones',
            'filtersSummary' => $fs,
            'sections' => [[
                'title' => 'Facturas vencidas por empresa',
                'columns' => [
                    ['key' => 'company_name',  'title' => 'Empresa',      'align' => 'left'],
                    ['key' => 'invoice_count', 'title' => '# Facturas',   'align' => 'right'],
                    ['key' => 'total_overdue', 'title' => 'Total vencido','align' => 'right', 'type' => 'money'],
                ],
                'rows' => $overdueQ->get(),
            ]],
        ];
    }
}
