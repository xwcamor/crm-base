<?php

namespace App\Jobs\Communication\Messages;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. Escribe fila por fila con `fputcsv` y `chunkById(1000)`.
 * Soporta cualquier volumen sin OOM-ear.
 */
class GenerateMessagesCsvJob extends BaseMessageExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'subject', 'audience_type', 'is_active', 'published_at', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'messages_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            // BOM UTF-8 para que Excel detecte encoding correctamente.
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'             => __('messages.id'),
                'subject'        => __('messages.subject'),
                'audience_type'  => __('messages.audience_type'),
                'audience_id'    => __('messages.audience_target'),
                'allow_replies'  => __('messages.allow_replies'),
                'is_active'      => __('messages.is_active'),
                'status'         => __('global.status'),
                'published_at'   => __('messages.published_at'),
                'expires_at'     => __('messages.expires_at'),
                'slug'           => 'Slug',
                'created_at'     => __('global.created_at'),
                'updated_at'     => __('global.updated_at'),
                'creator'        => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($messages) use ($handle, $columns, $tz) {
                foreach ($messages as $message) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'             => $message->id,
                        'subject'        => $message->subject,
                        'audience_type'  => $message->audience_type_text,
                        'audience_id'    => $message->audience_id ?? '',
                        'allow_replies'  => $message->allow_replies ? '1' : '0',
                        'is_active'      => $message->state_text,
                        'status'         => $message->status_text,
                        'published_at'   => $message->published_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'expires_at'     => $message->expires_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'slug'           => $message->slug,
                        'created_at'     => $message->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'     => $message->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'        => $message->creator?->name ?? '',
                        default          => $message->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'messages.id', 'id');

            fclose($handle);
            $handle = null;

            $content = file_get_contents($tempFile);
            $path    = 'downloads/' . $download->filename;

            \DB::transaction(function () use ($download, $path, $content) {
                Storage::disk($download->disk)->put($path, $content);
                $download->update(['path' => $path, 'status' => 'ready']);
            });
        } finally {
            if (is_resource($handle)) @fclose($handle);
            if (file_exists($tempFile)) @unlink($tempFile);
        }
    }
}
