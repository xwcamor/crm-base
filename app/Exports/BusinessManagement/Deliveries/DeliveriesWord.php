<?php

namespace App\Exports\BusinessManagement\Deliveries;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Genera un reporte .docx estilizado de Deliveries usando PhpWord directamente.
 * Clon de StockTakesWord adaptado al esquema de Delivery.
 */
class DeliveriesWord
{
    protected array $columnDefs;

    private const COLOR_BRAND       = '0A6ED1';
    private const COLOR_BRAND_DARK  = '085CAF';
    private const COLOR_SHELL       = '354A5F';
    private const COLOR_TEXT        = '32363A';
    private const COLOR_TEXT_SOFT   = '6A6D70';
    private const COLOR_BORDER      = 'E5E5E5';
    private const COLOR_ZEBRA       = 'F8FAFC';
    private const COLOR_FILTER_BG   = 'F0F6FB';

    public function generate(
        $deliveries,
        string $filename,
        array $options = [],
        array $filtersSummary = [],
        string $generatedBy = '-',
    ): void {
        $tz = $options['timezone'] ?? config('app.timezone', 'UTC');

        $this->columnDefs = [
            'id'              => ['heading' => __('deliveries.id'),              'value' => fn($d) => (string) $d->id],
            'reference'       => ['heading' => __('deliveries.reference'),       'value' => fn($d) => (string) $d->reference],
            'sales_order'     => ['heading' => __('deliveries.sales_order'),     'value' => fn($d) => (string) ($d->salesOrder?->reference ?? '-')],
            'warehouse'       => ['heading' => __('deliveries.warehouse'),       'value' => fn($d) => (string) ($d->warehouse?->name ?? '-')],
            'status'          => ['heading' => __('deliveries.status'),          'value' => fn($d) => __('deliveries.status_options.' . $d->status)],
            'carrier'         => ['heading' => __('deliveries.carrier'),         'value' => fn($d) => (string) $d->carrier],
            'tracking_number' => ['heading' => __('deliveries.tracking_number'), 'value' => fn($d) => (string) $d->tracking_number],
            'shipping_method' => ['heading' => __('deliveries.shipping_method'), 'value' => fn($d) => (string) $d->shipping_method],
            'shipping_cost'   => ['heading' => __('deliveries.shipping_cost'),   'value' => fn($d) => $d->shipping_cost !== null ? (string) $d->shipping_cost : ''],
            'shipped_at'      => ['heading' => __('deliveries.shipped_at'),      'value' => fn($d) => $d->shipped_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'delivered_at'    => ['heading' => __('deliveries.delivered_at'),    'value' => fn($d) => $d->delivered_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'signed_by_name'  => ['heading' => __('deliveries.signed_by_name'),  'value' => fn($d) => (string) $d->signed_by_name],
            'notes'           => ['heading' => __('deliveries.notes'),           'value' => fn($d) => (string) $d->notes],
            'slug'            => ['heading' => 'Slug',                           'value' => fn($d) => (string) $d->slug],
            'created_at'      => ['heading' => __('global.created_at'),          'value' => fn($d) => $d->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'updated_at'      => ['heading' => __('global.updated_at'),          'value' => fn($d) => $d->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'creator'         => ['heading' => __('global.created_by'),          'value' => fn($d) => $d->creator->name ?? '-'],
        ];

        $title         = $options['title']         ?? __('deliveries.export_title');
        $requestedCols = $options['columns']       ?? array_keys($this->columnDefs);
        $columns       = array_values(array_filter($requestedCols, fn($k) => isset($this->columnDefs[$k])));
        if (empty($columns)) $columns = array_keys($this->columnDefs);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);
        $phpWord->setDefaultParagraphStyle(['spaceAfter' => 0, 'lineHeight' => 1.25]);

        $section = $phpWord->addSection([
            'marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 900, 'marginRight' => 900,
        ]);

        $footer = $section->addFooter();
        $footerTable = $footer->addTable(['borderTopSize' => 6, 'borderTopColor' => self::COLOR_BORDER]);
        $footerTable->addRow();
        $footerTable->addCell(6000)->addText(
            config('app.name') . ' · ' . now()->setTimezone($tz)->format(\App\Support\Tz::DATE_FORMAT),
            ['size' => 8, 'color' => self::COLOR_TEXT_SOFT]
        );
        $cellRight = $footerTable->addCell(3000, ['valign' => 'top']);
        $rightP = $cellRight->addTextRun(['alignment' => Jc::END]);
        $rightP->addText(__('global.page') . ' ', ['size' => 8, 'color' => self::COLOR_TEXT_SOFT]);
        $rightP->addField('PAGE');
        $rightP->addText(' / ', ['size' => 8, 'color' => self::COLOR_TEXT_SOFT]);
        $rightP->addField('NUMPAGES');

        $brandTable = $section->addTable([
            'cellMargin' => 200, 'borderSize' => 0,
            'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        ]);
        $brandTable->addRow(800);
        $brandCell = $brandTable->addCell(9000, [
            'bgColor' => self::COLOR_SHELL, 'valign' => 'center',
        ]);
        $brandCell->addText($title, [
            'name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => 'FFFFFF',
        ], ['spaceAfter' => 60]);
        $brandCell->addText(
            __('global.generated_at') . ': ' . now()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) . ' · ' . trans_choice('global.records_in_report', count($deliveries), ['count' => count($deliveries)]),
            ['size' => 10, 'color' => 'CBD5E1']
        );

        $section->addTextBreak(1);
        $section->addText(
            __('global.created_by') . ': ' . $generatedBy,
            ['size' => 9, 'color' => self::COLOR_TEXT_SOFT, 'italic' => true]
        );

        if (!empty($filtersSummary) && ($options['include_filters_summary'] ?? true)) {
            $section->addTextBreak(1);
            $filterTable = $section->addTable(['cellMargin' => 180, 'borderSize' => 0]);
            $filterTable->addRow();
            $filterCell = $filterTable->addCell(9000, [
                'bgColor' => self::COLOR_FILTER_BG,
                'borderLeftSize' => 24, 'borderLeftColor' => self::COLOR_BRAND,
            ]);
            $filterCell->addText(mb_strtoupper(__('global.filters_applied')), [
                'size' => 8, 'bold' => true, 'color' => self::COLOR_BRAND,
            ], ['spaceAfter' => 80]);
            foreach ($filtersSummary as $f) {
                $line = $filterCell->addTextRun(['spaceAfter' => 40]);
                $line->addText($f['label'] . ': ', ['size' => 9, 'bold' => true, 'color' => self::COLOR_TEXT]);
                $line->addText($f['value'],          ['size' => 9, 'color' => self::COLOR_TEXT]);
            }
        }

        $section->addTextBreak(1);

        if (count($deliveries) === 0) {
            $section->addText(
                __('global.no_matching_records'),
                ['size' => 10, 'italic' => true, 'color' => self::COLOR_TEXT_SOFT],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 400]
            );
        } else {
            $phpWord->addTableStyle('DeliveriesTable', [
                'borderSize'  => 4, 'borderColor' => self::COLOR_BORDER,
                'cellMargin'  => 80, 'alignment' => JcTable::CENTER,
                'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::AUTO,
            ]);

            $table = $section->addTable('DeliveriesTable');
            $table->addRow(420, ['tblHeader' => true]);
            foreach ($columns as $col) {
                $cell = $table->addCell(null, [
                    'bgColor' => self::COLOR_BRAND, 'valign' => 'center',
                    'borderColor' => self::COLOR_BRAND_DARK, 'borderSize' => 4,
                ]);
                $cell->addText(
                    $this->columnDefs[$col]['heading'],
                    ['bold' => true, 'color' => 'FFFFFF', 'size' => 10],
                    ['alignment' => Jc::START, 'spaceAfter' => 0]
                );
            }

            foreach ($deliveries as $i => $d) {
                $table->addRow(360);
                $isEven = $i % 2 === 1;
                $rowBg  = $isEven ? self::COLOR_ZEBRA : 'FFFFFF';

                foreach ($columns as $col) {
                    $cell = $table->addCell(null, [
                        'bgColor' => $rowBg, 'valign' => 'center',
                        'borderColor' => self::COLOR_BORDER, 'borderSize' => 4,
                    ]);
                    $value = $this->columnDefs[$col]['value']($d);

                    if ($col === 'status') {
                        $color = $d->status === 'delivered' ? '1D7044'
                            : ($d->status === 'returned' ? 'C8281D'
                            : ($d->status === 'shipped' ? '0A6ED1' : self::COLOR_TEXT));
                        $cell->addText($value, ['size' => 9, 'bold' => true, 'color' => $color]);
                    } else {
                        $cell->addText((string) $value, ['size' => 9, 'color' => self::COLOR_TEXT]);
                    }
                }
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filename);
    }
}
