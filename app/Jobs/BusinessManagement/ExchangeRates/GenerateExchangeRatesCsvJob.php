<?php

namespace App\Jobs\BusinessManagement\ExchangeRates;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateExchangeRatesCsvJob extends BaseExchangeRateExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'base_code', 'quote_code', 'rate', 'valid_at', 'source', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'exchange_rates_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'         => __('exchange_rates.id'),
                'base_code'  => __('exchange_rates.base_code'),
                'quote_code' => __('exchange_rates.quote_code'),
                'rate'       => __('exchange_rates.rate'),
                'valid_at'   => __('exchange_rates.valid_at'),
                'source'     => __('exchange_rates.source'),
                'is_active'  => __('exchange_rates.is_active'),
                'slug'       => 'Slug',
                'created_at' => __('global.created_at'),
                'updated_at' => __('global.updated_at'),
                'creator'    => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($rates) use ($handle, $columns, $tz) {
                foreach ($rates as $rate) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'         => $rate->id,
                        'base_code'  => $rate->base_code,
                        'quote_code' => $rate->quote_code,
                        'rate'       => $rate->rate,
                        'valid_at'   => $rate->valid_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'source'     => $rate->source ?? '',
                        'is_active'  => $rate->is_active ? '1' : '0',
                        'slug'       => $rate->slug,
                        'created_at' => $rate->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at' => $rate->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'    => $rate->creator?->name ?? '',
                        default      => $rate->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'exchange_rates.id', 'id');

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
