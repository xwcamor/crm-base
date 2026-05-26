<?php

namespace App\Jobs\BusinessManagement\ProductCategories;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateProductCategoriesCsvJob extends BaseProductCategoryExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'parent', 'sort_order', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'product_categories_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'          => __('product_categories.id'),
                'name'        => __('product_categories.name'),
                'description' => __('product_categories.description'),
                'parent'      => __('product_categories.parent'),
                'sort_order'  => __('product_categories.sort_order'),
                'is_active'   => __('product_categories.is_active'),
                'slug'        => 'Slug',
                'created_at'  => __('global.created_at'),
                'updated_at'  => __('global.updated_at'),
                'creator'     => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($categories) use ($handle, $columns, $tz) {
                foreach ($categories as $category) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'          => $category->id,
                        'name'        => $category->name,
                        'description' => $category->description ?? '',
                        'parent'      => $category->parent?->name ?? '',
                        'sort_order'  => $category->sort_order,
                        'is_active'   => $category->is_active ? '1' : '0',
                        'slug'        => $category->slug,
                        'created_at'  => $category->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'  => $category->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'     => $category->creator?->name ?? '',
                        default       => $category->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'product_categories.id', 'id');

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
