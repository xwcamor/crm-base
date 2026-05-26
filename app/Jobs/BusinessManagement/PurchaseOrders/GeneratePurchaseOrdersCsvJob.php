<?php

namespace App\Jobs\BusinessManagement\PurchaseOrders;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

/**
 * Export streaming a CSV. Escribe fila por fila con `fputcsv` y `chunkById(1000)`.
 * Soporta cualquier volumen sin OOM-ear.
 */
class GeneratePurchaseOrdersCsvJob extends BasePurchaseOrderExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? [
            'id', 'reference', 'supplier', 'warehouse', 'status',
            'order_date', 'expected_delivery_date', 'currency_code', 'grand_total', 'created_at',
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'purchase_orders_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'                     => __('purchase_orders.id'),
                'reference'              => __('purchase_orders.reference'),
                'supplier'               => __('purchase_orders.supplier'),
                'warehouse'              => __('purchase_orders.warehouse'),
                'status'                 => __('purchase_orders.status'),
                'order_date'             => __('purchase_orders.order_date'),
                'expected_delivery_date' => __('purchase_orders.expected_delivery_date'),
                'currency_code'          => __('purchase_orders.currency'),
                'subtotal'               => __('purchase_orders.subtotal'),
                'tax_total'              => __('purchase_orders.tax_total'),
                'grand_total'            => __('purchase_orders.grand_total'),
                'owner'                  => __('purchase_orders.owner'),
                'creator'                => __('global.created_by'),
                'slug'                   => 'Slug',
                'created_at'             => __('global.created_at'),
                'updated_at'             => __('global.updated_at'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($orders) use ($handle, $columns, $tz) {
                foreach ($orders as $order) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'                     => $order->id,
                        'reference'              => $order->reference ?? '',
                        'supplier'               => $order->supplier?->name ?? '',
                        'warehouse'              => $order->warehouse?->name ?? '',
                        'status'                 => __('purchase_orders.status_options.' . $order->status),
                        'order_date'             => $order->order_date?->format('Y-m-d') ?? '',
                        'expected_delivery_date' => $order->expected_delivery_date?->format('Y-m-d') ?? '',
                        'currency_code'          => $order->currency_code ?? '',
                        'subtotal'               => $order->subtotal ?? '',
                        'tax_total'              => $order->tax_total ?? '',
                        'grand_total'            => $order->grand_total ?? '',
                        'owner'                  => $order->owner?->name ?? '',
                        'creator'                => $order->creator?->name ?? '',
                        'slug'                   => $order->slug,
                        'created_at'             => $order->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'             => $order->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        default                  => $order->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'purchase_orders.id', 'id');

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
