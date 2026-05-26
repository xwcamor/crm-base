<?php

namespace App\Services\SystemManagement;

use App\Jobs\SystemManagement\Plans\BulkPlansActionJob;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * PlanService — operaciones de negocio del modulo Plans (super only).
 *
 * Clon del patron de DiscountService adaptado a Plans:
 *   - Plans NO usa BelongsToTenant (catalogo global super-only).
 *   - `slug` es unico global (no per-tenant) y es la clave estable usada
 *     en tenants.plan/subscriptions.plan.
 *   - duplicate() genera un nuevo slug con sufijo -copy, ya que el slug
 *     original no es reutilizable.
 */
class PlanService
{
    public function create(array $data): Plan
    {
        $features   = $this->normalizeFeatures($data['features'] ?? []);
        $sortOrder  = $data['sort_order'] ?? ((int) Plan::max('sort_order') + 1);

        $plan = new Plan(array_merge($data, [
            'features'   => $features,
            'sort_order' => $sortOrder,
            'is_active'  => $data['is_active']  ?? true,
            'is_public'  => $data['is_public']  ?? true,
        ]));
        $plan->created_by = auth()->id();
        $plan->save();

        return $plan;
    }

    public function update(Plan $plan, array $data): Plan
    {
        $features = $this->normalizeFeatures($data['features'] ?? []);
        $plan->update(array_merge($data, [
            'features'  => $features,
            'is_active' => $data['is_active'] ?? $plan->is_active,
            'is_public' => $data['is_public'] ?? $plan->is_public,
        ]));
        return $plan;
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Plan $plan, string $reason): void
    {
        $plan->deleted_description = $reason;
        $plan->deleted_by          = auth()->id();
        $plan->is_active           = false;
        $plan->saveQuietly();
        $plan->delete();
    }

    public function restore(Plan $plan): Plan
    {
        $plan->deleted_description = null;
        $plan->deleted_by          = null;
        $plan->restore();
        return $plan;
    }

    /**
     * Hard delete. Audit ANTES del delete + transaccion + lockForUpdate
     * para evitar race con restore concurrente.
     */
    public function forceDelete(Plan $plan, string $reason): void
    {
        DB::transaction(function () use ($plan, $reason) {
            $locked = Plan::onlyTrashed()->where('id', $plan->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Plan {$plan->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Plan::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'slug'      => $locked->slug,
                    'name'      => $locked->name,
                    'is_active' => $locked->is_active,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'plans',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona un plan. El slug del clon es derivado del original con sufijo
     * "-copy" y un contador si ya existe (slug es unique global).
     */
    public function duplicate(Plan $plan): ?Plan
    {
        $baseName = $plan->name . ' (' . __('global.duplicate_suffix') . ')';

        return DB::transaction(function () use ($plan, $baseName) {
            $candidateName = $baseName;
            $i = 2;
            while (Plan::where('name', $candidateName)->lockForUpdate()->exists()) {
                $candidateName = $baseName . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'tagline', 'icon', 'color',
                'max_users', 'max_records_per_module', 'export_rate_limit',
                'support_level', 'features',
                'price_monthly', 'price_yearly', 'currency',
                'is_active', 'is_public',
            ];
            $clone = new Plan($plan->only($cloneAttrs));
            $clone->name       = $candidateName;
            $clone->slug       = $this->generateUniqueSlug($plan->slug);
            $clone->sort_order = (int) Plan::max('sort_order') + 1;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    /** Genera un slug unico global con sufijo -copy-N. */
    protected function generateUniqueSlug(string $base): string
    {
        $candidate = substr($base, 0, 50) . '_copy';
        $i = 1;
        while (Plan::withTrashed()->where('slug', $candidate)->exists()) {
            $i++;
            $candidate = substr($base, 0, 48) . '_copy_' . $i;
            if ($i > 100) {
                $candidate = substr($base, 0, 48) . '_' . substr(\Illuminate\Support\Str::random(8), 0, 8);
                break;
            }
        }
        return strtolower($candidate);
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkPlansActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPlansActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        // Plans con tenants activos no se borran ni en bulk — defensa
        // adicional sobre el guard del delete individual.
        $plans      = Plan::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($plans as $plan) {
            if ($plan->tenantsCount() > 0) {
                continue; // skip — bloqueado por dependents
            }
            $this->delete($plan, $reason);
            $deletedIds[] = $plan->id;
        }
        return ['queued' => false, 'count' => $plans->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPlansActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $plans   = Plan::whereIn('id', $ids)->get();
        $changed = 0;
        foreach ($plans as $plan) {
            if ((bool) $plan->is_active === $isActive) continue;
            $plan->update(['is_active' => $isActive]);
            $changed++;
        }
        return ['queued' => false, 'count' => $count, 'changed' => $changed];
    }

    /**
     * @return array{queued: bool, count: int, restored?: int}
     */
    public function bulkRestore(array $ids): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkPlansActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $plans = Plan::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($plans as $plan) {
            $this->restore($plan);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $plans->count()];
    }

    /**
     * Undo del ultimo borrado (60s window). Solo restaura filas que
     * matcheen deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $plans = Plan::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($plans as $plan) {
            $this->restore($plan);
            $restored[] = $plan->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + is_active. Transaccion para atomicidad,
     * skip filas sin cambio real para evitar audit noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = Plan::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $plan = $byId[$change['id']] ?? null;
                if (!$plan) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $plan->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $plan->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }

    /**
     * Single source de feature keys soportadas por Plans. Cualquier feature
     * que aparezca en el form debe estar aqui — el normalize garantiza un
     * bool explicito para cada una (false si falta en el payload).
     */
    public function featureKeys(): array
    {
        return [
            // Exports
            'export_csv', 'export_excel', 'export_pdf', 'export_word',
            'branded_exports',
            // Visibilidad
            'audit_log_view', 'saved_views',
            // Operaciones masivas + importar
            'bulk_operations', 'imports', 'edit_all',
            // Equipos
            'team_management',
            // Acceso programatico
            'api_access',
            // Automatizacion
            'automations',
            // Futuras (declaradas, sin gate todavia)
            'scheduled_exports', 'export_webhook_delivery', 'export_email_delivery',
            // Logica interna
            'extended_retention', 'higher_export_rate_limit',
        ];
    }

    /** Asegura todos los feature keys con bool explicito. Default false. */
    public function normalizeFeatures(array $raw): array
    {
        return collect($this->featureKeys())
            ->mapWithKeys(fn ($k) => [$k => (bool) ($raw[$k] ?? false)])
            ->all();
    }
}
