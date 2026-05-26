<?php

namespace App\Notifications;

use App\Models\Automation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * AutomationTriggered — notificación in-app que crea InAppNotificationAction.
 *
 * Contenido minimal: solo el NOMBRE de la automation y si la ejecución fue
 * exitosa. El cuerpo extendido del config (title/body con interpolación de
 * {count}/{list}) NO se guarda acá — el InAppNotificationAction lo manda
 * por email si elige ese canal, pero el in-app es solo "X se ejecutó".
 *
 * Razón: el bell del header debe ser informativo y compacto. Si el user
 * quiere detalle del último run, va al Show de la automation y ve el
 * historial completo.
 *
 * Auto-marca como leída: las automations no requieren ack del usuario,
 * son info ambient. El InAppNotificationAction se encarga de marcarlas
 * tras crearlas para que NO contribuyan al badge de unread.
 *
 * Solo channel `database`.
 */
class AutomationTriggered extends Notification
{
    use Queueable;

    /**
     * $channel describe POR QUÉ se crea esta notif in-app:
     *   'in_app' → InAppNotificationAction la creó como recordatorio dirigido
     *              a los destinatarios configurados.
     *   'email'  → EmailAction termino de enviar emails y este notify es
     *              confirmacion al creador de la automation ("se envio").
     * El bell pinta icono distinto segun el channel.
     */
    public function __construct(
        public Automation $automation,
        public int $recordsMatched,
        public bool $success = true,
        public string $channel = 'in_app',
        public int $emailRecipientsCount = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'           => $this->automation->name,
            'body'            => $this->buildBody(),
            'automation_id'   => $this->automation->id,
            'automation_name' => $this->automation->name,
            'records_matched' => $this->recordsMatched,
            'success'         => $this->success,
            'channel'         => $this->channel, // 'in_app' | 'email'
            'email_recipients_count' => $this->emailRecipientsCount,
            // Data source resuelto: el label viene del registry, no
            // hardcoded. Cuando se agrega un modulo nuevo (ej. Products)
            // su DataSourceContract::label() aparece aca automaticamente.
            'data_source_key'   => $this->automation->data_source,
            'data_source_label' => $this->resolveDataSourceLabel(),
            // Tenant info — badge cuando el notifiable es super y la notif
            // pertenece a otro workspace.
            'tenant_id'       => $this->automation->tenant_id,
            'tenant_name'     => $this->automation->tenant?->name,
            'category'        => 'automation',
        ];
    }

    /**
     * Resuelve el label del data source desde el registry. Si no hay fuente
     * configurada (automation sin data_source) o el key no existe en el
     * registry, devolvemos null y el frontend muestra solo el estado.
     */
    protected function resolveDataSourceLabel(): ?string
    {
        $key = $this->automation->data_source;
        if (!$key) return null;

        try {
            return app(\App\Services\Automations\DataSourceRegistry::class)
                ->resolve($key)
                ->label();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Body para el bell: "{Modulo} · {estado}" cuando hay data source,
     * solo "{estado}" si no hay. Frontend ya recibe esto pre-armado.
     */
    protected function buildBody(): string
    {
        $status = $this->buildStatus();
        $module = $this->resolveDataSourceLabel();
        if (!$module) return $status;
        return "{$module} · {$status}";
    }

    protected function buildStatus(): string
    {
        if (!$this->success) {
            return __('automations.notif_status_failed');
        }
        if ($this->channel === 'email') {
            return __('automations.notif_status_email_sent', [
                'count' => $this->emailRecipientsCount,
            ]);
        }
        return __('automations.notif_status_success', [
            'count' => $this->recordsMatched,
        ]);
    }
}
