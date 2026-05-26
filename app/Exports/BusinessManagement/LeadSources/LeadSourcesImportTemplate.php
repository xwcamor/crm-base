<?php

namespace App\Exports\BusinessManagement\LeadSources;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LeadSourcesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['name', 'description', 'category', 'sort_order', 'is_active'],
            ['Web Form',     'Formulario del sitio web',       'organic',  '10', '1'],
            ['Referido',     'Cliente referido por otro',      'referral', '20', '1'],
            ['Google Ads',   'Campañas de búsqueda paga',      'paid',     '30', '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $tooltips = [
                    'A1' => 'Nombre de la fuente (max 120). Unico por workspace.',
                    'B1' => 'Descripcion opcional (max 255).',
                    'C1' => 'Categoria libre (max 60). Ej: organic, paid, referral, event.',
                    'D1' => 'Orden numerico (entero). Por defecto 0.',
                    'E1' => __('imports.template_is_active_help'),
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
