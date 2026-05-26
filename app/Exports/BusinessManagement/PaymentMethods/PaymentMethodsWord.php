<?php

namespace App\Exports\BusinessManagement\PaymentMethods;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Reporte .docx de Payment Methods. SAP Fiori Quartz Light layout.
 */
class PaymentMethodsWord
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
        $methods,
        string $filename,
        array $options = [],
        array $filtersSummary = [],
        string $generatedBy = '—',
    ): void {
        $tz = $options['timezone'] ?? config('app.timezone', 'UTC');

        $this->columnDefs = [
            'id'                   => ['heading' => __('payment_methods.id'),                   'value' => fn($m) => (string) $m->id],
            'name'                 => ['heading' => __('payment_methods.name'),                 'value' => fn($m) => (string) $m->name],
            'code'                 => ['heading' => __('payment_methods.code'),                 'value' => fn($m) => (string) ($m->code ?? '')],
            'description'          => ['heading' => __('payment_methods.description'),          'value' => fn($m) => (string) ($m->description ?? '')],
            'integration_provider' => ['heading' => __('payment_methods.integration_provider'), 'value' => fn($m) => (string) ($m->integration_provider ?? '')],
            'requires_reference'   => ['heading' => __('payment_methods.requires_reference'),   'value' => fn($m) => $m->requires_reference ? __('global.yes') : __('global.no')],
            'sort_order'           => ['heading' => __('payment_methods.sort_order'),           'value' => fn($m) => (string) $m->sort_order],
            'is_active'            => ['heading' => __('payment_methods.is_active'),            'value' => fn($m) => $m->state_text],
            'slug'                 => ['heading' => 'Slug',                                      'value' => fn($m) => (string) $m->slug],
            'created_at'           => ['heading' => __('global.created_at'),                     'value' => fn($m) => $m->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'updated_at'           => ['heading' => __('global.updated_at'),                     'value' => fn($m) => $m->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) ?? ''],
            'creator'              => ['heading' => __('global.created_by'),                     'value' => fn($m) => $m->creator->name ?? '—'],
        ];

        $title         = $options['title']         ?? __('payment_methods.export_title');
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
            __('global.generated_at') . ': ' . now()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT) . ' · ' . trans_choice('global.records_in_report', count($methods), ['count' => count($methods)]),
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

        if (count($methods) === 0) {
            $section->addText(
                __('global.no_matching_records'),
                ['size' => 10, 'italic' => true, 'color' => self::COLOR_TEXT_SOFT],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 400]
            );
        } else {
            $phpWord->addTableStyle('PaymentMethodsTable', [
                'borderSize'      => 4,
                'borderColor'     => self::COLOR_BORDER,
                'cellMargin'      => 80,
                'alignment'       => JcTable::CENTER,
                'unit'            => \PhpOffice\PhpWord\SimpleType\TblWidth::AUTO,
            ]);

            $table = $section->addTable('PaymentMethodsTable');

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

            foreach ($methods as $i => $method) {
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
                    $value = $this->columnDefs[$col]['value']($method);

                    if ($col === 'is_active') {
                        $color = $method->is_active ? '1D7044' : 'C8281D';
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
