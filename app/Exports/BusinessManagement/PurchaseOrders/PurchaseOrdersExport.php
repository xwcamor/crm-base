<?php

namespace App\Exports\BusinessManagement\PurchaseOrders;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * XLSX styled SAP Fiori (title + subtitle + header azul + zebra rows).
 * Columnas dinamicas via $options['columns'].
 *
 * Clon de CustomersExport adaptado a PurchaseOrders (con relaciones supplier
 * y warehouse + columnas de totales/fechas/status).
 */
class PurchaseOrdersExport implements FromCollection, WithEvents, WithTitle
{
    protected $orders;
    protected array $options;
    protected array $columnDefs;
    protected array $activeColumns;
    protected string $tz;

    public function __construct($orders, array $options = [])
    {
        $this->orders  = $orders instanceof Collection ? $orders : collect($orders);
        $this->options = $options;

        if (!empty($options['timezone'])) {
            $this->tz = $options['timezone'];
        } else {
            $user = !empty($options['user_id'])
                ? \App\Models\User::withoutGlobalScopes()->find($options['user_id'])
                : null;
            $this->tz = \App\Support\Tz::for($user);
        }

        $tz = $this->tz;
        $this->columnDefs = [
            'id'                     => ['heading' => __('purchase_orders.id'),                      'value' => fn($o, $i) => $o->id],
            'reference'              => ['heading' => __('purchase_orders.reference'),              'value' => fn($o, $i) => (string) ($o->reference ?? '')],
            'supplier'               => ['heading' => __('purchase_orders.supplier'),               'value' => fn($o, $i) => (string) ($o->supplier?->name ?? '—')],
            'warehouse'              => ['heading' => __('purchase_orders.warehouse'),              'value' => fn($o, $i) => (string) ($o->warehouse?->name ?? '—')],
            'status'                 => ['heading' => __('purchase_orders.status'),                 'value' => fn($o, $i) => __('purchase_orders.status_options.' . $o->status)],
            'order_date'             => ['heading' => __('purchase_orders.order_date'),             'value' => fn($o, $i) => $o->order_date?->format('Y-m-d') ?? ''],
            'expected_delivery_date' => ['heading' => __('purchase_orders.expected_delivery_date'), 'value' => fn($o, $i) => $o->expected_delivery_date?->format('Y-m-d') ?? ''],
            'currency_code'          => ['heading' => __('purchase_orders.currency'),               'value' => fn($o, $i) => (string) ($o->currency_code ?? '')],
            'subtotal'               => ['heading' => __('purchase_orders.subtotal'),               'value' => fn($o, $i) => (float) ($o->subtotal ?? 0)],
            'tax_total'              => ['heading' => __('purchase_orders.tax_total'),              'value' => fn($o, $i) => (float) ($o->tax_total ?? 0)],
            'grand_total'            => ['heading' => __('purchase_orders.grand_total'),            'value' => fn($o, $i) => (float) ($o->grand_total ?? 0)],
            'owner'                  => ['heading' => __('purchase_orders.owner'),                  'value' => fn($o, $i) => (string) ($o->owner?->name ?? '—')],
            'slug'                   => ['heading' => 'Slug',                                       'value' => fn($o, $i) => $o->slug],
            'created_at'             => ['heading' => __('global.created_at'),                     'value' => fn($o, $i) => $o->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'updated_at'             => ['heading' => __('global.updated_at'),                     'value' => fn($o, $i) => $o->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'creator'                => ['heading' => __('global.created_by'),                     'value' => fn($o, $i) => $o->creator->name ?? '—'],
        ];

        $requested = $options['columns'] ?? array_keys($this->columnDefs);
        $this->activeColumns = array_values(array_filter(
            $requested,
            fn($k) => isset($this->columnDefs[$k])
        ));

        if (empty($this->activeColumns)) {
            $this->activeColumns = array_keys($this->columnDefs);
        }
    }

    public function collection()
    {
        $title    = $this->options['title'] ?? __('purchase_orders.export_title');
        $count    = $this->orders->count();
        $subtitle = sprintf(
            '%s · %s · %s',
            __('global.generated_at'),
            now()->setTimezone($this->tz)->format(\App\Support\Tz::DATETIME_FORMAT),
            trans_choice('global.records_in_report', $count, ['count' => $count])
        );

        $rows = collect();
        $rows->push([$title]);
        $rows->push([$subtitle]);
        $rows->push(['']);
        $rows->push(array_map(fn($k) => $this->columnDefs[$k]['heading'], $this->activeColumns));

        $i = 0;
        foreach ($this->orders as $order) {
            $rows->push(array_map(
                fn($k) => $this->columnDefs[$k]['value']($order, $i),
                $this->activeColumns
            ));
            $i++;
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr($this->options['title'] ?? __('purchase_orders.export_title'), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $colCount   = count($this->activeColumns);
                $lastColLet = $sheet->getCellByColumnAndRow($colCount, 1)->getColumn();
                $headerRow  = 4;
                $dataStart  = 5;
                $dataEnd    = $dataStart + $this->orders->count() - 1;

                if ($colCount > 1) {
                    $sheet->mergeCells("A1:{$lastColLet}1");
                }
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '32363A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                if ($colCount > 1) {
                    $sheet->mergeCells("A2:{$lastColLet}2");
                }
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'color' => ['rgb' => '6A6D70']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                $sheet->getStyle("A{$headerRow}:{$lastColLet}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(26);

                if ($dataEnd >= $dataStart) {
                    $sheet->getStyle("A{$dataStart}:{$lastColLet}{$dataEnd}")->applyFromArray([
                        'font' => ['size' => 10, 'color' => ['rgb' => '32363A']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E5E5']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    for ($row = $dataStart; $row <= $dataEnd; $row++) {
                        if (($row - $dataStart) % 2 === 1) {
                            $sheet->getStyle("A{$row}:{$lastColLet}{$row}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                            ]);
                        }
                        $sheet->getRowDimension($row)->setRowHeight(20);
                    }
                }

                for ($i = 1; $i <= $colCount; $i++) {
                    $letter = $sheet->getCellByColumnAndRow($i, 1)->getColumn();
                    $sheet->getColumnDimension($letter)->setAutoSize(true);
                }

                if (($this->options['autofilter'] ?? true) && $dataEnd >= $headerRow) {
                    $sheet->setAutoFilter("A{$headerRow}:{$lastColLet}{$dataEnd}");
                }
                if ($this->options['freeze_header'] ?? true) {
                    $sheet->freezePane('A' . ($headerRow + 1));
                }
            },
        ];
    }
}
