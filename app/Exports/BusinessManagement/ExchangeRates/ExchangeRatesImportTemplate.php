<?php

namespace App\Exports\BusinessManagement\ExchangeRates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExchangeRatesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['base_code', 'quote_code', 'rate', 'valid_at', 'source', 'is_active'],
            ['USD', 'PEN', '3.78',      '2026-05-19 09:00:00', 'manual',   '1'],
            ['USD', 'EUR', '0.92',      '2026-05-19 09:00:00', 'fixer.io', '1'],
            ['EUR', 'PEN', '4.11',      '2026-05-19 09:00:00', 'manual',   '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $tooltips = [
                    'A1' => 'Codigo ISO 4217 de la moneda base (3 letras MAYUSCULAS): USD, EUR, PEN.',
                    'B1' => 'Codigo ISO 4217 de la moneda quote (3 letras MAYUSCULAS). Debe diferir de base.',
                    'C1' => 'Tasa: cuantas unidades de quote equivalen a 1 unidad de base. Admite hasta 6 decimales.',
                    'D1' => 'Fecha/hora desde la que se aplica la tasa (YYYY-MM-DD HH:mm:ss).',
                    'E1' => 'Origen del dato: manual, fixer.io, BCRA, openexchangerates, otro. Default: manual.',
                    'F1' => __('imports.template_is_active_help'),
                ];
                foreach ($tooltips as $cell => $text) {
                    $comment = $sheet->getComment($cell);
                    $comment->setAuthor(__('imports.template_author'));
                    $comment->getText()->createTextRun($text);
                    $comment->setWidth('280pt');
                    $comment->setHeight('70pt');
                }
            },
        ];
    }
}
