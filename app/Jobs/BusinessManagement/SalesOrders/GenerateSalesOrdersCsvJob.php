<?php

namespace App\Jobs\BusinessManagement\SalesOrders;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateSalesOrdersCsvJob extends BaseSalesOrderExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'reference', 'company', 'status', 'payment_status', 'grand_total', 'order_date', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'sales_orders_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'             => __('sales_orders.id'),
                'reference'      => __('sales_orders.reference'),
                'company'        => __('sales_orders.company'),
                'warehouse'      => __('sales_orders.warehouse'),
                'status'         => __('sales_orders.status'),
                'payment_status' => __('sales_orders.payment_status'),
                'order_date'     => __('sales_orders.order_date'),
                'expected_delivery_date' => __('sales_orders.expected_delivery_date'),
                'currency_code'  => __('sales_orders.currency'),
                'subtotal'       => __('sales_orders.subtotal'),
                'tax_total'      => __('sales_orders.tax_total'),
                'grand_total'    => __('sales_orders.grand_total'),
                'slug'           => 'Slug',
                'created_at'     => __('global.created_at'),
                'updated_at'     => __('global.updated_at'),
                'creator'        => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($orders) use ($handle, $columns, $tz) {
                foreach ($orders as $order) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'             => $order->id,
                        'reference'      => $order->reference,
                        'company'        => $order->company?->name ?? '',
                        'warehouse'      => $order->warehouse?->name ?? '',
                        'status'         => __('sales_orders.status_options.' . $order->status),
                        'payment_status' => __('sales_orders.payment_status_options.' . $order->payment_status),
                        'order_date'     => $order->order_date?->format(\App\Support\Tz::DATE_FORMAT),
                        'expected_delivery_date' => $order->expected_delivery_date?->format(\App\Support\Tz::DATE_FORMAT),
                        'currency_code'  => $order->currency_code ?? '',
                        'subtotal'       => $order->subtotal,
                        'tax_total'      => $order->tax_total,
                        'grand_total'    => $order->grand_total,
                        'slug'           => $order->slug,
                        'created_at'     => $order->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'     => $order->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'        => $order->creator?->name ?? '',
                        default          => $order->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'sales_orders.id', 'id');

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
