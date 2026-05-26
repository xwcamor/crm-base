<?php

namespace App\Jobs\SystemManagement\Plans;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. Escribe fila por fila con `fputcsv` y
 * `chunkById(1000)`. Soporta cualquier volumen sin OOM-ear.
 */
class GeneratePlansCsvJob extends BasePlanExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'slug', 'name', 'max_users', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'plans_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            // BOM UTF-8 para que Excel lo abra con encoding correcto.
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'                     => __('plans.id'),
                'slug'                   => __('plans.slug'),
                'name'                   => __('plans.name'),
                'tagline'                => __('plans.tagline'),
                'support_level'          => __('plans.support_level'),
                'max_users'              => __('plans.max_users'),
                'max_records_per_module' => __('plans.max_records_per_module'),
                'export_rate_limit'      => __('plans.export_rate_limit'),
                'price_monthly'          => __('plans.price_monthly'),
                'price_yearly'           => __('plans.price_yearly'),
                'currency'               => __('plans.currency'),
                'is_active'              => __('plans.is_active'),
                'is_public'              => __('plans.is_public'),
                'sort_order'             => __('plans.sort_order'),
                'created_at'             => __('global.created_at'),
                'updated_at'             => __('global.updated_at'),
                'creator'                => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($plans) use ($handle, $columns, $tz) {
                foreach ($plans as $plan) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'                     => $plan->id,
                        'slug'                   => $plan->slug,
                        'name'                   => $plan->name,
                        'tagline'                => $plan->tagline ?? '',
                        'support_level'          => $plan->support_level ?: 'community',
                        'max_users'              => (int) $plan->getAttributes()['max_users'],
                        'max_records_per_module' => (int) $plan->getAttributes()['max_records_per_module'],
                        'export_rate_limit'      => $plan->export_rate_limit,
                        'price_monthly'          => (float) $plan->price_monthly,
                        'price_yearly'           => (float) $plan->price_yearly,
                        'currency'               => $plan->currency,
                        'is_active'              => $plan->is_active ? '1' : '0',
                        'is_public'              => $plan->is_public ? '1' : '0',
                        'sort_order'             => $plan->sort_order,
                        'created_at'             => $plan->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'             => $plan->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'                => $plan->creator?->name ?? '',
                        default                  => $plan->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'plans.id', 'id');

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
