<?php

namespace App\Exports\BusinessManagement\Discounts;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DiscountsImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['code', 'name', 'description', 'type', 'value', 'currency_code', 'min_purchase_amount', 'usage_limit', 'usage_per_customer', 'valid_from', 'valid_until', 'is_active'],
            ['WELCOME10', 'Bienvenida 10%', 'Cupon para nuevos clientes', 'percentage', '10', '', '', '100', '1', '', '2026-12-31 23:59:59', '1'],
            ['SHIP-FREE', 'Envio gratis', 'Promocion de envio', 'free_shipping', '0', '', '50', '', '', '', '', '1'],
            ['SAVE-20',   'Descuento $20', 'Cupon de monto fijo', 'fixed_amount', '20', 'USD', '100', '', '1', '', '', '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $tooltips = [
                    'A1' => 'Codigo unico del cupon (max 60). Se valida unique por workspace.',
                    'B1' => 'Nombre descriptivo (max 150).',
                    'C1' => 'Descripcion opcional (max 500).',
                    'D1' => 'Tipo: percentage | fixed_amount | free_shipping.',
                    'E1' => 'Valor numerico: % si percentage, monto si fixed.',
                    'F1' => 'Codigo de moneda ISO (3 letras) — solo si fixed_amount.',
                    'G1' => 'Monto minimo de la compra. Vacio = sin minimo.',
                    'H1' => 'Limite total de redenciones. Vacio = sin limite.',
                    'I1' => 'Limite de usos por cliente. Vacio = sin limite.',
                    'J1' => 'Fecha de inicio (YYYY-MM-DD HH:mm:ss). Vacio = inmediato.',
                    'K1' => 'Fecha de expiracion (YYYY-MM-DD HH:mm:ss). Vacio = sin fin.',
                    'L1' => __('imports.template_is_active_help'),
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
