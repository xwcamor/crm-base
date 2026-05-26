<?php

namespace App\Jobs\BusinessManagement\Deliveries;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateDeliveriesCsvJob extends BaseDeliveryExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'reference', 'sales_order', 'warehouse', 'status', 'carrier', 'tracking_number', 'shipped_at', 'delivered_at', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'deliveries_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'              => __('deliveries.id'),
                'reference'       => __('deliveries.reference'),
                'sales_order'     => __('deliveries.sales_order'),
                'warehouse'       => __('deliveries.warehouse'),
                'status'          => __('deliveries.status'),
                'carrier'         => __('deliveries.carrier'),
                'tracking_number' => __('deliveries.tracking_number'),
                'shipping_method' => __('deliveries.shipping_method'),
                'shipping_cost'   => __('deliveries.shipping_cost'),
                'shipped_at'      => __('deliveries.shipped_at'),
                'delivered_at'    => __('deliveries.delivered_at'),
                'signed_by_name'  => __('deliveries.signed_by_name'),
                'notes'           => __('deliveries.notes'),
                'slug'            => 'Slug',
                'created_at'      => __('global.created_at'),
                'updated_at'      => __('global.updated_at'),
                'creator'         => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($deliveries) use ($handle, $columns, $tz) {
                foreach ($deliveries as $d) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'              => $d->id,
                        'reference'       => $d->reference,
                        'sales_order'     => $d->salesOrder?->reference ?? '',
                        'warehouse'       => $d->warehouse?->name ?? '',
                        'status'          => __('deliveries.status_options.' . $d->status),
                        'carrier'         => (string) $d->carrier,
                        'tracking_number' => (string) $d->tracking_number,
                        'shipping_method' => (string) $d->shipping_method,
                        'shipping_cost'   => $d->shipping_cost !== null ? (string) $d->shipping_cost : '',
                        'shipped_at'      => $d->shipped_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'delivered_at'    => $d->delivered_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'signed_by_name'  => (string) $d->signed_by_name,
                        'notes'           => (string) $d->notes,
                        'slug'            => $d->slug,
                        'created_at'      => $d->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'      => $d->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'         => $d->creator?->name ?? '',
                        default           => $d->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'deliveries.id', 'id');

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
