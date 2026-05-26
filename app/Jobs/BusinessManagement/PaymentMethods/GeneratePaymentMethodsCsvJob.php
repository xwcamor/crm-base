<?php

namespace App\Jobs\BusinessManagement\PaymentMethods;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePaymentMethodsCsvJob extends BasePaymentMethodExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'code', 'integration_provider', 'requires_reference', 'sort_order', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'payment_methods_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'                   => __('payment_methods.id'),
                'name'                 => __('payment_methods.name'),
                'code'                 => __('payment_methods.code'),
                'description'          => __('payment_methods.description'),
                'integration_provider' => __('payment_methods.integration_provider'),
                'requires_reference'   => __('payment_methods.requires_reference'),
                'sort_order'           => __('payment_methods.sort_order'),
                'is_active'            => __('payment_methods.is_active'),
                'slug'                 => 'Slug',
                'created_at'           => __('global.created_at'),
                'updated_at'           => __('global.updated_at'),
                'creator'              => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($methods) use ($handle, $columns, $tz) {
                foreach ($methods as $method) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'                   => $method->id,
                        'name'                 => $method->name,
                        'code'                 => $method->code ?? '',
                        'description'          => $method->description ?? '',
                        'integration_provider' => $method->integration_provider ?? '',
                        'requires_reference'   => $method->requires_reference ? '1' : '0',
                        'sort_order'           => $method->sort_order,
                        'is_active'            => $method->is_active ? '1' : '0',
                        'slug'                 => $method->slug,
                        'created_at'           => $method->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'           => $method->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'              => $method->creator?->name ?? '',
                        default                => $method->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'payment_methods.id', 'id');

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
