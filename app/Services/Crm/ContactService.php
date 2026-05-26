<?php

namespace App\Services\Crm;

use App\Jobs\Crm\Contacts\BulkContactsActionJob;
use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * ContactService — operaciones de negocio del modulo Contacts.
 *
 * Clon del patron de RegionService/RoleService: el controller queda thin
 * y delega aca toda la mutacion de datos. Mantiene los audit logs cerca
 * de la operacion (Auditable trait dispara en created/updated/deleted/
 * restored; force_delete escribe el audit manual).
 *
 * NO maneja exports/imports/list: esa es orquestacion HTTP y vive en el
 * controller.
 */
class ContactService
{
    public function create(array $data): Contact
    {
        return DB::transaction(function () use ($data) {
            // Regla "1 primary por company": si el contact entra marcado
            // como primary y tiene company_id, des-marcar a cualquier otro
            // primary previo de esa misma company. Pattern auto-demote (los
            // CRMs enterprise comunes — HubSpot, Salesforce — lo hacen asi
            // en vez de rechazar con validation error).
            $this->demoteOtherPrimariesIfNeeded($data, null);

            $contact = new Contact($data);
            $contact->created_by = auth()->id();
            $contact->save();
            return $contact;
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data) {
            $this->demoteOtherPrimariesIfNeeded($data, $contact);
            $contact->update($data);
            return $contact;
        });
    }

    /**
     * Des-marca cualquier otro contact con is_primary_for_company=true en la
     * misma company. Idempotente: si el contact que se esta editando ya es
     * primary, no toca a nadie (excluded via $exceptId).
     */
    protected function demoteOtherPrimariesIfNeeded(array $data, ?Contact $current): void
    {
        $wantsPrimary = !empty($data['is_primary_for_company'])
            && filter_var($data['is_primary_for_company'], FILTER_VALIDATE_BOOLEAN);
        if (!$wantsPrimary) return;

        $companyId = $data['company_id'] ?? $current?->company_id;
        if (!$companyId) return;

        Contact::query()
            ->where('company_id', $companyId)
            ->where('is_primary_for_company', true)
            ->when($current?->id, fn ($q, $id) => $q->where('id', '!=', $id))
            ->update(['is_primary_for_company' => false]);
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Contact $contact, string $reason): void
    {
        $contact->deleted_description = $reason;
        $contact->deleted_by          = auth()->id();
        $contact->is_active           = false;
        $contact->saveQuietly();
        $contact->delete();
    }

    public function restore(Contact $contact): Contact
    {
        $contact->deleted_description = null;
        $contact->deleted_by          = null;
        $contact->restore();
        return $contact;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Contact $contact, string $reason): void
    {
        DB::transaction(function () use ($contact, $reason) {
            $locked = Contact::onlyTrashed()->where('id', $contact->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Contact {$contact->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Contact::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'name' => $locked->name,
                    'slug' => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'contacts',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

public function duplicate(Contact $contact): ?Contact
    {
        $base    = $contact->name . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($contact, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Contact::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(name) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'is_active',
            ];
            $clone = new Contact($contact->only($cloneAttrs));
            $clone->name       = $candidate;
            $clone->created_by = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline. El umbral vive en
    // BulkContactsActionJob::asyncThreshold() (Setting global -> config).

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkContactsActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkContactsActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $contacts  = Contact::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($contacts as $contact) {
            $this->delete($contact, $reason);
            $deletedIds[] = $contact->id;
        }
        return ['queued' => false, 'count' => $contacts->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkContactsActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $contacts = Contact::whereIn('id', $ids)->get();
        $changed   = 0;
        foreach ($contacts as $contact) {
            if ((bool) $contact->is_active === $isActive) continue;
            $contact->update(['is_active' => $isActive]);
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
            BulkContactsActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $contacts = Contact::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($contacts as $contact) {
            $this->restore($contact);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $contacts->count()];
    }

    /**
     * Undo dentro del window de 60s. Defense in depth: solo restaura las filas
     * que matchean deleted_by = userId, no cualquier id del claim.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $contacts = Contact::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($contacts as $contact) {
            $this->restore($contact);
            $restored[] = $contact->id;
        }
        return $restored;
    }

    /**
     * Batch update de name + is_active. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids   = array_column($changes, 'id');
            $byId  = Contact::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $contact = $byId[$change['id']] ?? null;
                if (!$contact) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['name', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $contact->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $contact->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
