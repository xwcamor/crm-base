<?php

namespace App\Exports\BusinessManagement\PriceLists;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PriceListsImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['name', 'description', 'currency_code', 'global_discount_pct', 'priority', 'valid_from', 'valid_until', 'is_default', 'is_active'],
            ['Standard Retail',  'Lista por defecto retail',     'USD', '0',  '10', '', '',                     '1', '1'],
            ['Wholesale 25 off', 'Mayoristas con 25% global',     'USD', '25', '20', '', '',                     '0', '1'],
            ['Enterprise Q4',    'Cuentas enterprise — Q4 promo', 'USD', '15', '30', '', '2026-12-31 23:59:59', '0', '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $tooltips = [
                    'A1' => 'Nombre unico de la lista (max 150). Se valida unique por workspace, case+accent insensitive.',
                    'B1' => 'Descripcion opcional (max 500).',
                    'C1' => 'Codigo de moneda ISO (3 letras). Vacio = moneda por defecto del tenant.',
                    'D1' => 'Descuento global aplicado a todos los productos. Numero 0-100.',
                    'E1' => 'Prioridad para resolver conflictos cuando un cliente califica para varias listas. Mayor = gana.',
                    'F1' => 'Fecha de inicio (YYYY-MM-DD HH:mm:ss). Vacio = activa de inmediato.',
                    'G1' => 'Fecha de expiracion (YYYY-MM-DD HH:mm:ss). Vacio = sin fecha de fin.',
                    'H1' => 'Marca esta lista como default para clientes sin lista asignada. Solo una puede ser default por tenant.',
                    'I1' => __('imports.template_is_active_help'),
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
