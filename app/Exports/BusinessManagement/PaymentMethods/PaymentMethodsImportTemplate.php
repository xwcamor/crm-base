<?php

namespace App\Exports\BusinessManagement\PaymentMethods;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentMethodsImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['name', 'code', 'description', 'integration_provider', 'requires_reference', 'sort_order', 'is_active'],
            ['Transferencia bancaria', 'transfer',  'Pago por transferencia',     '',            '1', '10', '1'],
            ['Tarjeta de credito',     'card',      'Pago con tarjeta',           'stripe',      '0', '20', '1'],
            ['Efectivo',               'cash',      'Pago en efectivo',           '',            '0', '30', '1'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $tooltips = [
                    'A1' => 'Nombre del metodo de pago (max 100). Unico por workspace.',
                    'B1' => 'Codigo corto opcional (max 30). Ej: transfer, card, cash.',
                    'C1' => 'Descripcion opcional (max 500).',
                    'D1' => 'Provider de integracion opcional (max 60). Ej: stripe, mercadopago, paypal.',
                    'E1' => '1 = requiere referencia (ej: nro de transferencia), 0 = no requiere.',
                    'F1' => 'Orden numerico (entero). Por defecto 0.',
                    'G1' => __('imports.template_is_active_help'),
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
