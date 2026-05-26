<?php

namespace App\Notifications;

use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * DownloadFailed — se dispara cuando un Download pasa a status='failed'.
 *
 * Solo canal `mail`. La bell ya muestra el fallo desde la query directa
 * de la tabla `downloads` (ver HandleInertiaRequests::buildInboxPayload).
 */
class DownloadFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Download $download) {}

    public function via(object $notifiable): array
    {
        if (!\App\Models\Setting::getBool('notifications.email_enabled', true)) {
            return [];
        }
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('global.download_failed') . ' — ' . $this->download->filename)
            ->error()
            ->greeting(__('global.greeting_hi', ['name' => $notifiable->name ?? '']))
            ->line(__('mail.download_failed_intro', [
                'type' => strtoupper($this->download->type),
            ]))
            ->line(__('mail.download_failed_filename', [
                'filename' => $this->download->filename,
            ]))
            ->line(__('mail.download_failed_reason', [
                'reason' => $this->download->error_message
                    ? mb_substr($this->download->error_message, 0, 200)
                    : __('global.unknown_error'),
            ]))
            ->line(__('mail.download_failed_retry'));
    }
}
