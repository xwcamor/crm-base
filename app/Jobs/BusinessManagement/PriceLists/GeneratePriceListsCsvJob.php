<?php

namespace App\Jobs\BusinessManagement\PriceLists;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. A diferencia de Excel/PDF/Word (cargan en memoria),
 * este job escribe fila por fila con `fputcsv` y `chunkById(1000)`. Soporta
 * cualquier volumen sin OOM-ear.
 */
class GeneratePriceListsCsvJob extends BasePriceListExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'currency_code', 'global_discount_pct', 'priority', 'is_default', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'price_lists_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'                  => __('price_lists.id'),
                'name'                => __('price_lists.name'),
                'description'         => __('price_lists.description'),
                'currency_code'       => __('price_lists.currency'),
                'global_discount_pct' => __('price_lists.global_discount_pct'),
                'priority'            => __('price_lists.priority'),
                'valid_from'          => __('price_lists.valid_from'),
                'valid_until'         => __('price_lists.valid_until'),
                'is_default'          => __('price_lists.is_default'),
                'is_active'           => __('price_lists.is_active'),
                'slug'                => 'Slug',
                'created_at'          => __('global.created_at'),
                'updated_at'          => __('global.updated_at'),
                'creator'             => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($priceLists) use ($handle, $columns, $tz) {
                foreach ($priceLists as $priceList) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'                  => $priceList->id,
                        'name'                => $priceList->name,
                        'description'         => $priceList->description ?? '',
                        'currency_code'       => $priceList->currency_code ?? '',
                        'global_discount_pct' => $priceList->global_discount_pct,
                        'priority'            => $priceList->priority,
                        'valid_from'          => $priceList->valid_from?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'valid_until'         => $priceList->valid_until?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'is_default'          => $priceList->is_default ? '1' : '0',
                        'is_active'           => $priceList->is_active ? '1' : '0',
                        'slug'                => $priceList->slug,
                        'created_at'          => $priceList->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'          => $priceList->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'             => $priceList->creator?->name ?? '',
                        default               => $priceList->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'price_lists.id', 'id');

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
