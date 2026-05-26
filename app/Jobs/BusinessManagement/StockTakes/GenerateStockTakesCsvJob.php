<?php

namespace App\Jobs\BusinessManagement\StockTakes;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateStockTakesCsvJob extends BaseStockTakeExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'reference', 'warehouse', 'status', 'started_at', 'completed_at', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'stock_takes_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'           => __('stock_takes.id'),
                'reference'    => __('stock_takes.reference'),
                'warehouse'    => __('stock_takes.warehouse'),
                'status'       => __('stock_takes.status'),
                'started_at'   => __('stock_takes.started_at'),
                'completed_at' => __('stock_takes.completed_at'),
                'note'         => __('stock_takes.note'),
                'slug'         => 'Slug',
                'created_at'   => __('global.created_at'),
                'updated_at'   => __('global.updated_at'),
                'creator'      => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($takes) use ($handle, $columns, $tz) {
                foreach ($takes as $take) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'           => $take->id,
                        'reference'    => $take->reference,
                        'warehouse'    => $take->warehouse?->name ?? '',
                        'status'       => __('stock_takes.status_options.' . $take->status),
                        'started_at'   => $take->started_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'completed_at' => $take->completed_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'note'         => (string) $take->note,
                        'slug'         => $take->slug,
                        'created_at'   => $take->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'   => $take->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'      => $take->creator?->name ?? '',
                        default        => $take->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'stock_takes.id', 'id');

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
