<?php

namespace App\Services\Communication;

use App\Jobs\Communication\Messages\BulkMessagesActionJob;
use App\Models\AuditLog;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MessageService — business logic del modulo Messages + Inbox.
 *
 * El controller del super crea el Message y luego llama publish() para
 * materializar los recipients segun audience_type. La separacion permite que
 * el super edite un draft (sin recipients) y publique despues.
 *
 * Tier 1 parity: incluye create/update/delete/restore/forceDelete/duplicate
 * + bulk ops + editAllUpdate + undoLastDelete (clon del patron DiscountService).
 */
class MessageService
{
    // ─── CRUD basico ─────────────────────────────────────────────────────────

    public function create(array $data): Message
    {
        $publishNow = (bool) ($data['publish_now'] ?? false);
        unset($data['publish_now']);

        $data['created_by']    = auth()->id();
        $data['allow_replies'] = (bool) ($data['allow_replies'] ?? false);
        $data['is_active']     = (bool) ($data['is_active'] ?? true);

        if (($data['audience_type'] ?? null) === Message::AUDIENCE_GLOBAL) {
            $data['audience_id'] = null;
        }

        return DB::transaction(function () use ($data, $publishNow) {
            $message = Message::create($data);
            if ($publishNow) {
                $this->publish($message);
            }
            return $message;
        });
    }

    public function update(Message $message, array $data): Message
    {
        $publishNow = (bool) ($data['publish_now'] ?? false);
        unset($data['publish_now']);

        $data['allow_replies'] = (bool) ($data['allow_replies'] ?? false);
        $data['is_active']     = (bool) ($data['is_active'] ?? true);

        if (($data['audience_type'] ?? null) === Message::AUDIENCE_GLOBAL) {
            $data['audience_id'] = null;
        }

        DB::transaction(function () use ($message, $data, $publishNow) {
            $message->update($data);
            // NO re-creamos recipients si ya estaba publicado: cambiar la
            // audiencia despues de publicar es UB. El super puede crear un
            // mensaje nuevo si quiere otra audiencia.
            if ($publishNow && !$message->isPublished()) {
                $this->publish($message);
            }
        });

        return $message->fresh();
    }

    /**
     * Soft-delete con motivo. saveQuietly() evita un audit log `updated`
     * duplicado justo antes del `deleted`.
     */
    public function delete(Message $message, string $reason): void
    {
        $message->deleted_description = $reason;
        $message->deleted_by          = auth()->id();
        $message->is_active           = false;
        $message->saveQuietly();
        $message->delete();
    }

    public function restore(Message $message): Message
    {
        $message->deleted_description = null;
        $message->deleted_by          = null;
        $message->restore();
        return $message;
    }

    /**
     * Hard delete. Audit ANTES del delete (sobrevive al borrado) + transaccion
     * para atomicidad. lockForUpdate previene race con un restore concurrente.
     */
    public function forceDelete(Message $message, string $reason): void
    {
        DB::transaction(function () use ($message, $reason) {
            $locked = Message::onlyTrashed()->where('id', $message->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException("Message {$message->id} no longer available for force-delete");
            }

            AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Message::class,
                'auditable_id'   => $locked->id,
                'event'          => 'force_deleted',
                'old_values'     => [
                    'subject' => $locked->subject,
                    'slug'    => $locked->slug,
                ],
                'new_values'     => null,
                'url'            => request()?->fullUrl(),
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
                'note'           => $reason,
                'module'         => 'messages',
                'created_at'     => now(),
            ]);

            $locked->forceDelete();
        });
    }

    /**
     * Clona el message. Sufijo "(copia)" con sanity guard de 100 intentos.
     * El nuevo clone arranca como draft (published_at = null) y is_active = false
     * para que el super lo revise antes de publicar.
     */
    public function duplicate(Message $message): ?Message
    {
        $base    = $message->subject . ' (' . __('global.duplicate_suffix') . ')';
        $isPgsql = DB::getDriverName() === 'pgsql';

        return DB::transaction(function () use ($message, $base, $isPgsql) {
            $candidate = $base;
            $i = 2;

            while (true) {
                $exists = Message::query()
                    ->when($isPgsql,
                        fn ($q) => $q->whereRaw('unaccent(LOWER(subject)) = unaccent(LOWER(?))', [$candidate]),
                        fn ($q) => $q->whereRaw('LOWER(subject) = LOWER(?)', [$candidate]),
                    )
                    ->lockForUpdate()
                    ->exists();

                if (!$exists) break;
                $candidate = $base . ' ' . $i;
                $i++;
                if ($i > 100) return null;
            }

            $cloneAttrs = [
                'body', 'audience_type', 'audience_id',
                'allow_replies', 'expires_at',
            ];
            $clone = new Message($message->only($cloneAttrs));
            $clone->subject      = $candidate;
            $clone->is_active    = false;
            $clone->published_at = null;
            $clone->created_by   = auth()->id();
            $clone->save();

            return $clone;
        });
    }

    // ─── Publish + audiencia (logica especifica del modulo) ──────────────────

    /**
     * Publica el mensaje:
     *   1. Calcula recipients segun audience_type (excluye api/system users).
     *   2. Inserta filas en message_recipients (skip duplicates).
     *   3. Setea published_at = now() si no estaba.
     *
     * Idempotente: si se llama dos veces, no duplica recipients (unique key).
     *
     * @return int cantidad de recipients creados en esta ejecucion.
     */
    public function publish(Message $message): int
    {
        $userIds = $this->resolveAudienceUserIds($message);

        if (empty($userIds)) {
            if (!$message->isPublished()) {
                $message->forceFill(['published_at' => now()])->save();
            }
            return 0;
        }

        $existing = MessageRecipient::where('message_id', $message->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->all();
        $newIds = array_diff($userIds, $existing);

        $now = now();
        $rows = [];
        foreach ($newIds as $uid) {
            $rows[] = [
                'message_id' => $message->id,
                'user_id'    => $uid,
                'read_at'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $batch) {
                MessageRecipient::insert($batch);
            }
        }

        if (!$message->isPublished()) {
            $message->forceFill(['published_at' => $now])->save();
        }

        return count($newIds);
    }

    /**
     * Resuelve la lista de user_ids destinatarios para el mensaje.
     * Excluye en todos los casos los users con rol `api` (system users).
     *
     * @return int[]
     */
    public function resolveAudienceUserIds(Message $message): array
    {
        $query = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true);

        $query->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'api');
        });

        switch ($message->audience_type) {
            case Message::AUDIENCE_GLOBAL:
                break;

            case Message::AUDIENCE_TENANT:
                if (!$message->audience_id) return [];
                $query->where('tenant_id', $message->audience_id);
                break;

            case Message::AUDIENCE_USER:
                if (!$message->audience_id) return [];
                $query->where('id', $message->audience_id);
                break;

            default:
                return [];
        }

        return $query->pluck('id')->all();
    }

    public function markAsRead(User $user, Message $message): void
    {
        MessageRecipient::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function markAllAsRead(User $user): int
    {
        return MessageRecipient::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Cantidad de mensajes no leidos del user, considerando solo mensajes
     * activos, publicados y no vencidos. Lo consume el bell de notificaciones.
     *
     * Acepta `int $userId` o el modelo `User`.
     */
    public function unreadCountForUser(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        return MessageRecipient::query()
            ->where('message_recipients.user_id', $userId)
            ->whereNull('message_recipients.read_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('messages')
                    ->whereColumn('messages.id', 'message_recipients.message_id')
                    ->whereNull('messages.deleted_at')
                    ->where('messages.is_active', true)
                    ->whereNotNull('messages.published_at')
                    ->where(function ($qq) {
                        $qq->whereNull('messages.expires_at')
                           ->orWhere('messages.expires_at', '>', now());
                    });
            })
            ->count();
    }

    public function inboxFor(User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Message::query()
            ->select('messages.*', 'message_recipients.read_at as read_at')
            ->join('message_recipients', 'message_recipients.message_id', '=', 'messages.id')
            ->where('message_recipients.user_id', $userId)
            ->whereNull('messages.deleted_at')
            ->where('messages.is_active', true)
            ->whereNotNull('messages.published_at')
            ->where(function ($q) {
                $q->whereNull('messages.expires_at')
                  ->orWhere('messages.expires_at', '>', now());
            })
            ->orderByDesc('messages.published_at');
    }

    // ─── Bulk ops ──────────────────────────────────────────────────────────
    //
    // Auto-async: si count(ids) excede el umbral, dispatchamos el job y
    // devolvemos un payload "queued" para que el controller redirija con
    // mensaje de cola. Bajo el umbral, corre inline.

    public function shouldDispatchAsync(int $count): bool
    {
        return $count > BulkMessagesActionJob::asyncThreshold();
    }

    /**
     * @return array{queued: bool, count: int, deleted?: int[]}
     */
    public function bulkDelete(array $ids, string $reason): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkMessagesActionJob::dispatch(
                (int) auth()->id(),
                'delete',
                $ids,
                ['reason' => $reason],
            );
            return ['queued' => true, 'count' => $count, 'deleted' => []];
        }

        $messages   = Message::whereIn('id', $ids)->get();
        $deletedIds = [];
        foreach ($messages as $message) {
            $this->delete($message, $reason);
            $deletedIds[] = $message->id;
        }
        return ['queued' => false, 'count' => $messages->count(), 'deleted' => $deletedIds];
    }

    /**
     * @return array{queued: bool, count: int, changed?: int}
     */
    public function bulkSetActive(array $ids, bool $isActive): array
    {
        $count = count($ids);

        if ($this->shouldDispatchAsync($count)) {
            BulkMessagesActionJob::dispatch(
                (int) auth()->id(),
                'set_active',
                $ids,
                ['is_active' => $isActive],
            );
            return ['queued' => true, 'count' => $count];
        }

        $messages = Message::whereIn('id', $ids)->get();
        $changed  = 0;
        foreach ($messages as $message) {
            if ((bool) $message->is_active === $isActive) continue;
            $message->update(['is_active' => $isActive]);
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
            BulkMessagesActionJob::dispatch(
                (int) auth()->id(),
                'restore',
                $ids,
                [],
            );
            return ['queued' => true, 'count' => $count];
        }

        $messages = Message::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($messages as $message) {
            $this->restore($message);
        }
        return ['queued' => false, 'count' => $count, 'restored' => $messages->count()];
    }

    /**
     * Undo dentro del window. Defense in depth: solo restaura las filas
     * que matchean deleted_by = userId.
     *
     * @param int[] $claimIds
     * @return int[] ids efectivamente restaurados
     */
    public function undoLastDelete(array $claimIds, int $userId): array
    {
        $messages = Message::onlyTrashed()
            ->whereIn('id', $claimIds)
            ->where('deleted_by', $userId)
            ->get();

        $restored = [];
        foreach ($messages as $message) {
            $this->restore($message);
            $restored[] = $message->id;
        }
        return $restored;
    }

    /**
     * Batch update de subject + is_active. Persistencia en transaccion para
     * atomicidad. Skip filas sin cambio real para evitar audit log noise.
     *
     * @return int touched count
     */
    public function editAllUpdate(array $changes): int
    {
        $touched = 0;

        DB::transaction(function () use ($changes, &$touched) {
            $ids  = array_column($changes, 'id');
            $byId = Message::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($changes as $change) {
                $message = $byId[$change['id']] ?? null;
                if (!$message) continue;

                $patch = array_filter(
                    array_intersect_key($change, array_flip(['subject', 'is_active'])),
                    fn ($v) => $v !== null,
                );
                if (empty($patch)) continue;

                $hasChange = false;
                foreach ($patch as $k => $v) {
                    if ((string) $message->{$k} !== (string) $v) { $hasChange = true; break; }
                }
                if (!$hasChange) continue;

                $message->fill($patch)->save();
                $touched++;
            }
        });

        return $touched;
    }
}
