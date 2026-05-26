<?php

namespace App\Jobs\BusinessManagement\Discounts;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. A diferencia de Excel/PDF/Word (cargan en memoria),
 * este job escribe fila por fila con `fputcsv` y `chunkById(1000)`. Soporta
 * cualquier volumen sin OOM-ear.
 */
class GenerateDiscountsCsvJob extends BaseDiscountExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'code', 'name', 'type', 'value', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'discounts_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'                  => __('discounts.id'),
                'code'                => __('discounts.code'),
                'name'                => __('discounts.name'),
                'description'         => __('discounts.description'),
                'type'                => __('discounts.type'),
                'value'               => __('discounts.value'),
                'currency_code'       => __('discounts.currency'),
                'min_purchase_amount' => __('discounts.min_purchase_amount'),
                'usage_limit'         => __('discounts.usage_limit'),
                'usage_per_customer'  => __('discounts.usage_per_customer'),
                'usage_count'         => __('discounts.usage_count'),
                'valid_from'          => __('discounts.valid_from'),
                'valid_until'         => __('discounts.valid_until'),
                'is_active'           => __('discounts.is_active'),
                'slug'                => 'Slug',
                'created_at'          => __('global.created_at'),
                'updated_at'          => __('global.updated_at'),
                'creator'             => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($discounts) use ($handle, $columns, $tz) {
                foreach ($discounts as $discount) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'                  => $discount->id,
                        'code'                => $discount->code,
                        'name'                => $discount->name,
                        'description'         => $discount->description ?? '',
                        'type'                => $discount->type_text,
                        'value'               => $discount->value,
                        'currency_code'       => $discount->currency_code ?? '',
                        'min_purchase_amount' => $discount->min_purchase_amount ?? '',
                        'usage_limit'         => $discount->usage_limit ?? '',
                        'usage_per_customer'  => $discount->usage_per_customer ?? '',
                        'usage_count'         => $discount->usage_count,
                        'valid_from'          => $discount->valid_from?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'valid_until'         => $discount->valid_until?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? '',
                        'is_active'           => $discount->is_active ? '1' : '0',
                        'slug'                => $discount->slug,
                        'created_at'          => $discount->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'          => $discount->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'             => $discount->creator?->name ?? '',
                        default               => $discount->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'discounts.id', 'id');

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
