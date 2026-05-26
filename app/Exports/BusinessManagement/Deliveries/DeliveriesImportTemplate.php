<?php

namespace App\Exports\BusinessManagement\Deliveries;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para imports de Deliveries.
 *
 * Columnas (cabecera):
 *   - reference        (opcional, unique-per-tenant. Si vacio, se autogenera)
 *   - sales_order_ref  (obligatorio, reference de la sales order)
 *   - warehouse_code   (obligatorio, codigo del almacen de origen)
 *   - status           (opcional, default pending)
 *   - carrier          (opcional)
 *   - tracking_number  (opcional)
 *   - shipping_method  (opcional)
 *   - notes            (opcional)
 *
 * Por simplicidad, el template no soporta lineas (cantidades por producto).
 * Las entregas creadas via import arrancan sin lineas. El usuario las edita
 * manualmente desde el formulario y registra las cantidades a despachar.
 */
class DeliveriesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['reference', 'sales_order_ref', 'warehouse_code', 'status', 'carrier', 'tracking_number', 'shipping_method', 'notes'],
            ['',                'OV-2026-0001', 'WH-01', 'pending', 'DHL',   '1Z999AA10123456784', 'Estandar', 'Envio prioritario'],
            ['DEL-2026-0001',   'OV-2026-0002', 'WH-02', 'shipped', 'FedEx', '7849-2103-4856',     'Express',  'Cliente VIP'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastCol = 'H';
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
                    'A1' => 'Codigo interno de la entrega (ej. DEL-2026-0001). Opcional. Si lo dejas vacio, el sistema asigna el siguiente correlativo.',
                    'B1' => 'Reference de la orden de venta. Obligatorio. Tiene que existir en el modulo de Ordenes de Venta.',
                    'C1' => 'Codigo del almacen de origen. Obligatorio. Tiene que existir en Almacenes.',
                    'D1' => 'Estado de la entrega. Valores: pending, picking, packed, shipped, delivered, returned. Default: pending.',
                    'E1' => 'Transportista (DHL, FedEx, OCA, propia). Opcional. Max 100 chars.',
                    'F1' => 'Numero de tracking del transportista. Opcional. Max 80 chars.',
                    'G1' => 'Modalidad: estandar, express, mismo dia, retiro en sucursal. Opcional. Max 60 chars.',
                    'H1' => 'Observaciones internas sobre el envio. Opcional. Max 1000 chars.',
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
