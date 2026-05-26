<?php

namespace App\Notifications;

use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * DownloadReady — se dispara cuando un Download pasa a status='ready'.
 *
 * Solo canal `mail`. La bell ya muestra el item desde la query directa
 * de la tabla `downloads` (ver HandleInertiaRequests::buildInboxPayload),
 * asi que NO usamos el canal `database` para evitar duplicar el badge.
 *
 * ShouldQueue mantiene el job de export snappy: el envio del mail va al
 * queue como step separado.
 */
class DownloadReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Download $download) {}

    public function via(object $notifiable): array
    {
        // Respeta el setting global `notifications.email_enabled`. Si esta
        // off, la notification no envia (return []). El bell del header
        // mantiene su info via el shared prop `inbox` que lee Downloads.
        if (!\App\Models\Setting::getBool('notifications.email_enabled', true)) {
            return [];
        }
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('notifications.download', $this->download->id);

        return (new MailMessage)
            ->subject(__('global.download_ready') . ' — ' . $this->download->filename)
            ->greeting(__('global.greeting_hi', ['name' => $notifiable->name ?? '']))
            ->line(__('mail.download_ready_intro', [
                'type' => strtoupper($this->download->type),
            ]))
            ->line(__('mail.download_ready_filename', [
                'filename' => $this->download->filename,
            ]))
            ->action(__('global.download'), $url)
            ->line(__('mail.download_ready_expires', [
                'date' => optional($this->download->expires_at)->format('Y-m-d H:i') ?? '—',
            ]))
            ->line(__('mail.download_ready_footer'));
    }
}
