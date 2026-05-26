<?php

namespace App\Jobs\BusinessManagement\ProductVariants;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateProductVariantsCsvJob extends BaseProductVariantExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'sku', 'product', 'price', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'product_variants_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'         => __('product_variants.id'),
                'name'       => __('product_variants.name'),
                'sku'        => __('product_variants.sku'),
                'barcode'    => __('product_variants.barcode'),
                'product'    => __('product_variants.product'),
                'attributes' => __('product_variants.attributes'),
                'cost'       => __('product_variants.cost'),
                'price'      => __('product_variants.price'),
                'sort_order' => __('product_variants.sort_order'),
                'is_active'  => __('product_variants.is_active'),
                'slug'       => 'Slug',
                'created_at' => __('global.created_at'),
                'updated_at' => __('global.updated_at'),
                'creator'    => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($variants) use ($handle, $columns, $tz) {
                foreach ($variants as $variant) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'         => $variant->id,
                        'name'       => $variant->name,
                        'sku'        => $variant->sku,
                        'barcode'    => $variant->barcode ?? '',
                        'product'    => $variant->product?->name ?? '',
                        'attributes' => is_array($variant->attributes) ? json_encode($variant->attributes, JSON_UNESCAPED_UNICODE) : '',
                        'cost'       => $variant->cost,
                        'price'      => $variant->price,
                        'sort_order' => $variant->sort_order,
                        'is_active'  => $variant->is_active ? '1' : '0',
                        'slug'       => $variant->slug,
                        'created_at' => $variant->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at' => $variant->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'    => $variant->creator?->name ?? '',
                        default      => $variant->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'product_variants.id', 'id');

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
