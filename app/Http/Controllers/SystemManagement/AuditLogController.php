<?php

namespace App\Http\Controllers\SystemManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * AuditLogController — read-only ledger across all modules.
 *
 * Access policy: super OR admin. Regular users / clients' workers
 * can NEVER see this. Defense-in-depth: route middleware + abort_unless here.
 */
class AuditLogController extends Controller
{
    /**
     * Modulos del core (system_management). El admin NO puede auditarlos —
     * solo super. El admin ve logs de modulos no-core de su tenant.
     */
    private const CORE_MODULES = [
        'regions', 'languages', 'countries', 'locales',
        'tenants', 'plans', 'system_modules', 'settings',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasRole('super') || $user->hasRole('admin')),
            403
        );

        $isSuper = $user->hasRole('super');

        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        $query = AuditLog::query()
            ->with(['user:id,name,email'])
            ->select([
                'id', 'user_id', 'event', 'auditable_type', 'auditable_id',
                'module', 'old_values', 'new_values', 'url', 'ip_address',
                'user_agent', 'note', 'created_at',
            ]);

        // Tenant scope: admin solo ve logs de users de SU tenant.
        // (Super ve todo, incluidos logs propios y de otros tenants.)
        // Ademas: admin NO puede auditar modulos del core — solo super.
        if (! $isSuper) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->withoutGlobalScopes()->where('tenant_id', $user->tenant_id);
            });
            $query->where(function ($q) {
                $q->whereNotIn('module', self::CORE_MODULES)
                  ->orWhereNull('module');
            });
        }

        // Filters
        if ($request->filled('module')) {
            $query->where('module', $request->get('module'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->get('event'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->get('auditable_id'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        // Distinct module + event lists para filter dropdowns — también scoped por tenant.
        $modulesQuery = AuditLog::query()->whereNotNull('module');
        $eventsQuery  = AuditLog::query();
        if (! $isSuper) {
            $tenantScope = function ($q) use ($user) {
                $q->withoutGlobalScopes()->where('tenant_id', $user->tenant_id);
            };
            $modulesQuery->whereHas('user', $tenantScope)
                ->whereNotIn('module', self::CORE_MODULES);
            $eventsQuery->whereHas('user', $tenantScope);
        }
        $modules = $modulesQuery->distinct()->orderBy('module')->pluck('module');
        $events  = $eventsQuery->distinct()->orderBy('event')->pluck('event');

        return inertia('AuditLogs/Index', [
            'logs'    => $logs,
            'modules' => $modules,
            'events'  => $events,
            'filters' => [
                'module'       => $request->get('module', ''),
                'event'        => $request->get('event', ''),
                'user_id'      => $request->get('user_id', ''),
                'auditable_id' => $request->get('auditable_id', ''),
                'date_from'    => $request->get('date_from', ''),
                'date_to'      => $request->get('date_to', ''),
                'per_page'     => $perPage,
            ],
        ]);
    }
}
