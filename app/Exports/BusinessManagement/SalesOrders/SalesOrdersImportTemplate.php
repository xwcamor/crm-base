<?php

namespace App\Exports\BusinessManagement\SalesOrders;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para imports de Sales Orders.
 *
 * Columnas (cabecera):
 *   - reference       (opcional, unique-per-tenant. Si vacio, se autogenera)
 *   - company_name    (obligatorio, matchea por nombre case+accent insensitive)
 *   - warehouse_code  (obligatorio, codigo del almacen origen)
 *   - status          (opcional, default pending)
 *   - payment_status  (opcional, default unpaid)
 *   - order_date      (opcional, default hoy. Formato YYYY-MM-DD)
 *   - expected_delivery_date (opcional)
 *   - currency_code   (opcional, ISO 4217 de 3 chars)
 *   - notes           (opcional)
 *
 * Por simplicidad, el template no soporta lineas (items). Las OVs creadas
 * via import quedan sin lineas y deben editarse manualmente para agregarlas.
 */
class SalesOrdersImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['reference', 'company_name', 'warehouse_code', 'status', 'payment_status', 'order_date', 'expected_delivery_date', 'currency_code', 'notes'],
            ['',                'Empresa Acme S.A.',   'WH-01', 'pending',    'unpaid', '2026-01-15', '2026-01-22', 'USD', 'Pedido inicial'],
            ['OV-2026-0001',    'Distribuidora Beta',  'WH-02', 'processing', 'partial','2026-01-16', '2026-01-30', 'PEN', ''],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastCol = 'I';
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
                    'A1' => 'Codigo interno de la OV (ej. OV-2026-0001). Opcional. Si lo dejas vacio, el sistema asigna el siguiente correlativo.',
                    'B1' => 'Nombre de la empresa cliente. Obligatorio. El sistema busca case+accent insensitive.',
                    'C1' => 'Codigo del almacen origen. Obligatorio. Tiene que existir en el modulo de Almacenes.',
                    'D1' => 'Estado de la OV. Valores: pending, processing, partially_shipped, shipped, delivered, cancelled, closed. Default: pending.',
                    'E1' => 'Estado de pago. Valores: unpaid, partial, paid, overdue. Default: unpaid.',
                    'F1' => 'Fecha de la orden (YYYY-MM-DD). Default: hoy.',
                    'G1' => 'Fecha esperada de entrega (YYYY-MM-DD). Opcional. Debe ser >= order_date.',
                    'H1' => 'Codigo ISO 4217 (USD, PEN, EUR, etc). 3 caracteres. Opcional.',
                    'I1' => 'Notas visibles para el cliente. Opcional. Max 2000 chars.',
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
