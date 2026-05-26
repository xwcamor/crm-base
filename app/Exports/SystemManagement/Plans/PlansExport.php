<?php

namespace App\Exports\SystemManagement\Plans;

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
 * Columnas dinamicas via $options['columns']. Clon de DiscountsExport.
 */
class PlansExport implements FromCollection, WithEvents, WithTitle
{
    protected $plans;
    protected array $options;
    protected array $columnDefs;
    protected array $activeColumns;
    protected string $tz;

    public function __construct($plans, array $options = [])
    {
        $this->plans   = $plans instanceof Collection ? $plans : collect($plans);
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
            'id'                     => ['heading' => __('plans.id'),                     'value' => fn($p, $i) => $p->id],
            'slug'                   => ['heading' => __('plans.slug'),                   'value' => fn($p, $i) => $p->slug],
            'name'                   => ['heading' => __('plans.name'),                   'value' => fn($p, $i) => $p->name],
            'tagline'                => ['heading' => __('plans.tagline'),                'value' => fn($p, $i) => $p->tagline ?? ''],
            'support_level'          => ['heading' => __('plans.support_level'),          'value' => fn($p, $i) => __('plans.support_' . ($p->support_level ?: 'community'))],
            'max_users'              => ['heading' => __('plans.max_users'),              'value' => fn($p, $i) => (int) $p->getAttributes()['max_users']],
            'max_records_per_module' => ['heading' => __('plans.max_records_per_module'), 'value' => fn($p, $i) => (int) $p->getAttributes()['max_records_per_module']],
            'export_rate_limit'      => ['heading' => __('plans.export_rate_limit'),      'value' => fn($p, $i) => $p->export_rate_limit],
            'price_monthly'          => ['heading' => __('plans.price_monthly'),          'value' => fn($p, $i) => (float) $p->price_monthly],
            'price_yearly'           => ['heading' => __('plans.price_yearly'),           'value' => fn($p, $i) => (float) $p->price_yearly],
            'currency'               => ['heading' => __('plans.currency'),               'value' => fn($p, $i) => $p->currency],
            'is_active'              => ['heading' => __('plans.is_active'),              'value' => fn($p, $i) => $p->is_active ? __('global.active') : __('global.inactive')],
            'is_public'              => ['heading' => __('plans.is_public'),              'value' => fn($p, $i) => $p->is_public  ? __('global.yes')    : __('global.no')],
            'sort_order'             => ['heading' => __('plans.sort_order'),             'value' => fn($p, $i) => $p->sort_order],
            'created_at'             => ['heading' => __('global.created_at'),            'value' => fn($p, $i) => $p->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'updated_at'             => ['heading' => __('global.updated_at'),            'value' => fn($p, $i) => $p->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT)],
            'creator'                => ['heading' => __('global.created_by'),            'value' => fn($p, $i) => $p->creator->name ?? '—'],
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
        $title    = $this->options['title'] ?? __('plans.export_title');
        $count    = $this->plans->count();
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
        foreach ($this->plans as $plan) {
            $rows->push(array_map(
                fn($k) => $this->columnDefs[$k]['value']($plan, $i),
                $this->activeColumns
            ));
            $i++;
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr($this->options['title'] ?? __('plans.export_title'), 0, 31);
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
                $dataEnd    = $dataStart + $this->plans->count() - 1;

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
