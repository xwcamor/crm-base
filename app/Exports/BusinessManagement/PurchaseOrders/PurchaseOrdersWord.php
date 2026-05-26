<?php

namespace App\Exports\BusinessManagement\PurchaseOrders;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Generates a styled .docx report of PurchaseOrders using PhpWord.
 * Layout: SAP Fiori Quartz Light (mismo patron que CustomersWord).
 */
class PurchaseOrdersWord
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
        $orders,
        string $filename,
        array $options = [],
        array $filtersSummary = [],
        string $generatedBy = '—',
    ): void {
        $tz = $options['timezone'] ?? config('app.timezone', 'UTC');

        $this->columnDefs = [
            'id'                     => ['heading' => __('purchase_orders.id'),                      'value' => fn($o) => (string) $o->id],
            'reference'              => ['heading' => __('purchase_orders.reference'),              'value' => fn($o) => (string) ($o->reference ?? '')],
            'supplier'               => ['heading' => __('purchase_orders.supplier'),               'value' => fn($o) => (string) ($o->supplier?->name ?? '—')],
            'warehouse'              => ['heading' => __('purchase_orders.warehouse'),              'value' => fn($o) => (string) ($o->warehouse?->name ?? '—')],
            'status'                 => ['heading' => __('purchase_orders.status'),                 'value' => fn($o) => __('purchase_orders.status_options.' . $o->status)],
            'order_date'             => ['heading' => __('purchase_orders.order_date'),             'value' => fn($o) => $o->order_date?->format('Y-m-d') ?? ''],
            'expected_delivery_date' => ['heading' => __('purchase_orders.expected_delivery_date'), 'value' => fn($o) => $o->expected_delivery_date?->format('Y-m-d') ?? ''],
            'currency_code'          => ['heading' => __('purchase_orders.currency'),               'value' => fn($o) => (string) ($o->currency_code ?? '')],
            'subtotal'               => ['heading' => __('purchase_orders.subtotal'),               'value' => fn($o) => number_format((float) ($o->subtotal ?? 0), 2)],
            'tax_total'              => ['heading' => __('purchase_orders.tax_total'),              'value' => fn($o) => number_format((float) ($o->tax_total ?? 0), 2)],
            'grand_total'            => ['heading' => __('purchase_orders.grand_total'),            'value' => fn($o) => number_format((float) ($o->grand_total ?? 0), 2)],
            'owner'                  => ['heading' => __('purchase_orders.owner'),                  'value' => fn($o) => (string) ($o->owner?->name ?? '—')],
            'slug'                   => ['heading' => 'Slug',                                       'value' => fn($o) => (string) $o->slug],
            'created_at'             => ['heading' => __('global.created_at'),                     'value' => fn($o) => $o->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'updated_at'             => ['heading' => __('global.updated_at'),                     'value' => fn($o) => $o->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'creator'                => ['heading' => __('global.created_by'),                     'value' => fn($o) => $o->creator->name ?? '—'],
        ];

        $title         = $options['title']         ?? __('purchase_orders.export_title');
        $requestedCols = $options['columns']       ?? array_keys($this->columnDefs);
        $columns       = array_values(array_filter($requestedCols, fn($k) => isset($this->columnDefs[$k])));
        if (empty($columns)) {
            $columns = array_keys($this->columnDefs);
        }

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        $phpWord->setDefaultParagraphStyle([
            'spaceAfter' => 0,
            'lineHeight' => 1.25,
        ]);

        $section = $phpWord->addSection([
            'marginTop'    => 1000,
            'marginBottom' => 1000,
            'marginLeft'   => 900,
            'marginRight'  => 900,
        ]);

        $footer = $section->addFooter();
        $footerTable = $footer->addTable(['borderTopSize' => 6, 'borderTopColor' => self::COLOR_BORDER]);
        $footerTable->addRow();
        $footerTable->addCell(6000)
            ->addText(
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
            'cellMargin'   => 200,
            'borderSize'   => 0,
            'unit'         => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        ]);
        $brandTable->addRow(800);
        $brandCell = $brandTable->addCell(9000, [
            'bgColor' => self::COLOR_SHELL,
            'valign'  => 'center',
        ]);
        $brandCell->addText($title, [
            'name' => 'Calibri', 'size' => 22, 'bold' => true, 'color' => 'FFFFFF',
        ], ['spaceAfter' => 60]);
        $brandCell->addText(
            __('global.generated_at') . ': ' . now()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) . ' · ' . trans_choice('global.records_in_report', count($orders), ['count' => count($orders)]),
            ['size' => 10, 'color' => 'CBD5E1']
        );

        $section->addTextBreak(1);
        $section->addText(
            __('global.created_by') . ': ' . $generatedBy,
            ['size' => 9, 'color' => self::COLOR_TEXT_SOFT, 'italic' => true]
        );

        if (!empty($filtersSummary) && ($options['include_filters_summary'] ?? true)) {
            $section->addTextBreak(1);

            $filterTable = $section->addTable([
                'cellMargin' => 180,
                'borderSize' => 0,
            ]);
            $filterTable->addRow();
            $filterCell = $filterTable->addCell(9000, [
                'bgColor'           => self::COLOR_FILTER_BG,
                'borderLeftSize'    => 24,
                'borderLeftColor'   => self::COLOR_BRAND,
            ]);
            $filterCell->addText(mb_strtoupper(__('global.filters_applied')), [
                'size'  => 8,
                'bold'  => true,
                'color' => self::COLOR_BRAND,
            ], ['spaceAfter' => 80]);
            foreach ($filtersSummary as $f) {
                $line = $filterCell->addTextRun(['spaceAfter' => 40]);
                $line->addText($f['label'] . ': ', ['size' => 9, 'bold' => true, 'color' => self::COLOR_TEXT]);
                $line->addText($f['value'],          ['size' => 9, 'color' => self::COLOR_TEXT]);
            }
        }

        $section->addTextBreak(1);

        if (count($orders) === 0) {
            $section->addText(
                __('global.no_matching_records'),
                ['size' => 10, 'italic' => true, 'color' => self::COLOR_TEXT_SOFT],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 400]
            );
        } else {
            $phpWord->addTableStyle('POTable', [
                'borderSize'      => 4,
                'borderColor'     => self::COLOR_BORDER,
                'cellMargin'      => 80,
                'alignment'       => JcTable::CENTER,
                'unit'            => \PhpOffice\PhpWord\SimpleType\TblWidth::AUTO,
            ]);

            $table = $section->addTable('POTable');

            $table->addRow(420, ['tblHeader' => true]);
            foreach ($columns as $col) {
                $cell = $table->addCell(null, [
                    'bgColor'     => self::COLOR_BRAND,
                    'valign'      => 'center',
                    'borderColor' => self::COLOR_BRAND_DARK,
                    'borderSize'  => 4,
                ]);
                $cell->addText(
                    $this->columnDefs[$col]['heading'],
                    ['bold' => true, 'color' => 'FFFFFF', 'size' => 10],
                    ['alignment' => Jc::START, 'spaceAfter' => 0]
                );
            }

            foreach ($orders as $i => $order) {
                $table->addRow(360);
                $isEven = $i % 2 === 1;
                $rowBg  = $isEven ? self::COLOR_ZEBRA : 'FFFFFF';

                foreach ($columns as $col) {
                    $cell = $table->addCell(null, [
                        'bgColor'     => $rowBg,
                        'valign'      => 'center',
                        'borderColor' => self::COLOR_BORDER,
                        'borderSize'  => 4,
                    ]);
                    $value = $this->columnDefs[$col]['value']($order);
                    $cell->addText((string) $value, ['size' => 9, 'color' => self::COLOR_TEXT]);
                }
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filename);
    }
}
