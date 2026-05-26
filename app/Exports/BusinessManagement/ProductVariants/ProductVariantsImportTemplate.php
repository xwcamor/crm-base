<?php

namespace App\Exports\BusinessManagement\ProductVariants;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductVariantsImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['sku', 'name', 'product_sku', 'barcode', 'attributes', 'cost', 'price', 'sort_order', 'is_active'],
            ['CAM-OXF-S-AZ', 'Camisa Oxford S azul', 'CAM-OXF',  '779000000001', '{"size":"S","color":"azul"}',   '12.5', '24.9', '1',  '1'],
            ['CAM-OXF-M-AZ', 'Camisa Oxford M azul', 'CAM-OXF',  '779000000002', '{"size":"M","color":"azul"}',   '12.5', '24.9', '2',  '1'],
            ['CAM-OXF-L-AZ', 'Camisa Oxford L azul', 'CAM-OXF',  '779000000003', '{"size":"L","color":"azul"}',   '12.5', '24.9', '3',  '1'],
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
                    'A1' => 'SKU unico de la variante (max 60). Unico por workspace.',
                    'B1' => 'Nombre descriptivo de la variante (max 200).',
                    'C1' => 'SKU del producto padre. Debe existir previamente.',
                    'D1' => 'Codigo de barras (max 60). Opcional.',
                    'E1' => 'Atributos como JSON. Ejemplo: {"size":"M","color":"rojo"}. Opcional.',
                    'F1' => 'Costo (decimal). Opcional. Si vacio usa el del producto padre.',
                    'G1' => 'Precio (decimal). Opcional. Si vacio usa el del producto padre.',
                    'H1' => 'Orden numerico (entero). Por defecto 0.',
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
