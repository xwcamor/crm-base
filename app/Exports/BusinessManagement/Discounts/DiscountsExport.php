<?php

namespace App\Exports\BusinessManagement\Discounts;

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
 */
class DiscountsExport implements FromCollection, WithEvents, WithTitle
{
    protected $discounts;
    protected array $options;
    protected array $columnDefs;
    protected array $activeColumns;
    protected string $tz;

    public function __construct($discounts, array $options = [])
    {
        $this->discounts = $discounts instanceof Collection ? $discounts : collect($discounts);
        $this->options   = $options;

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
            'id'                  => ['heading' => __('discounts.id'),                  'value' => fn($c, $i) => $c->id],
            'code'                => ['heading' => __('discounts.code'),                'value' => fn($c, $i) => $c->code],
            'name'                => ['heading' => __('discounts.name'),                'value' => fn($c, $i) => $c->name],
            'description'         => ['heading' => __('discounts.description'),         'value' => fn($c, $i) => $c->description ?? ''],
            'type'                => ['heading' => __('discounts.type'),                'value' => fn($c, $i) => $c->type_text],
            'value'               => ['heading' => __('discounts.value'),               'value' => fn($c, $i) => $c->value],
            'currency_code'       => ['heading' => __('discounts.currency'),            'value' => fn($c, $i) => $c->currency_code ?? ''],
            'min_purchase_amount' => ['heading' => __('discounts.min_purchase_amount'), 'value' => fn($c, $i) => $c->min_purchase_amount ?? ''],
            'usage_limit'         => ['heading' => __('discounts.usage_limit'),         'value' => fn($c, $i) => $c->usage_limit ?? ''],
            'usage_per_customer'  => ['heading' => __('discounts.usage_per_customer'),  'value' => fn($c, $i) => $c->usage_per_customer ?? ''],
            'usage_count'         => ['heading' => __('discounts.usage_count'),         'value' => fn($c, $i) => $c->usage_count],
            'valid_from'          => ['heading' => __('discounts.valid_from'),          'value' => fn($c, $i) => $c->valid_from?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'valid_until'         => ['heading' => __('discounts.valid_until'),         'value' => fn($c, $i) => $c->valid_until?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'is_active'           => ['heading' => __('discounts.is_active'),           'value' => fn($c, $i) => $c->state_text],
            'slug'                => ['heading' => 'Slug',                              'value' => fn($c, $i) => $c->slug],
            'created_at'          => ['heading' => __('global.created_at'),             'value' => fn($c, $i) => $c->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'updated_at'          => ['heading' => __('global.updated_at'),             'value' => fn($c, $i) => $c->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'creator'             => ['heading' => __('global.created_by'),             'value' => fn($c, $i) => $c->creator->name ?? '—'],
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

    /** Filas 1-3: titulo/subtitulo/spacer. Fila 4: header. Fila 5+: data. */
    public function collection()
    {
        $title    = $this->options['title'] ?? __('discounts.export_title');
        $count    = $this->discounts->count();
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
        foreach ($this->discounts as $discount) {
            $rows->push(array_map(
                fn($k) => $this->columnDefs[$k]['value']($discount, $i),
                $this->activeColumns
            ));
            $i++;
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr($this->options['title'] ?? __('discounts.export_title'), 0, 31);
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
                $dataEnd    = $dataStart + $this->discounts->count() - 1;

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
