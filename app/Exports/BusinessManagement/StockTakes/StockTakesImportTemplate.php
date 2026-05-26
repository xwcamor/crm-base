<?php

namespace App\Exports\BusinessManagement\StockTakes;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para imports de Stock Takes.
 *
 * Columnas (cabecera):
 *   - reference       (opcional, unique-per-tenant. Si vacio, se autogenera)
 *   - warehouse_code  (obligatorio, codigo del almacen del conteo)
 *   - status          (opcional, default draft)
 *   - note            (opcional)
 *
 * Por simplicidad, el template no soporta lineas (qty_counted por producto).
 * Los conteos creados via import quedan en status 'draft' y arrancan con
 * lineas auto-generadas desde el StockLevel del warehouse al momento del
 * import. El usuario completa qty_counted manualmente en el detalle.
 */
class StockTakesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['reference', 'warehouse_code', 'status', 'note'],
            ['',                'WH-01', 'draft',       'Conteo mensual'],
            ['IN-2026-0001',    'WH-02', 'in_progress', 'Auditoría trimestral'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastCol = 'D';
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $comments = [
                    'A1' => 'Codigo interno del conteo (ej. IN-2026-0001). Opcional. Si lo dejas vacio, el sistema asigna el siguiente correlativo.',
                    'B1' => 'Codigo del almacen. Obligatorio. Tiene que existir en el modulo de Almacenes.',
                    'C1' => 'Estado del conteo. Valores: draft, in_progress, completed, cancelled. Default: draft.',
                    'D1' => 'Notas u objetivo del conteo. Opcional. Max 1000 chars.',
                ];
                foreach ($comments as $cellAddr => $text) {
                    $comment = $sheet->getComment($cellAddr);
                    $comment->setAuthor(__('imports.template_author'));
                    $comment->getText()->createTextRun($text);
                    $comment->setWidth('300pt');
                    $comment->setHeight('80pt');
                }
            },
        ];
    }
}
