<?php

namespace App\Exports\BusinessManagement\PurchaseOrders;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla XLSX descargable para imports de PurchaseOrders.
 *
 * Importer "header-only" — solo se importan datos de cabecera de la OC
 * (sin lineas). Las lineas se editan despues desde la UI.
 *
 * Columnas:
 *   - reference        (opcional, max 30, unique por tenant — se autogenera si vacio)
 *   - supplier_name    (obligatorio, busca Company por nombre case-insensitive)
 *   - warehouse_code   (obligatorio, busca Warehouse por code o name)
 *   - status           (obligatorio, uno de PurchaseOrder::STATUSES; default 'draft')
 *   - order_date       (obligatorio, ISO yyyy-mm-dd)
 *   - expected_delivery_date (opcional, ISO yyyy-mm-dd)
 *   - currency_code    (opcional, 3 chars; default = currency del tenant)
 *   - payment_terms_days (opcional, int)
 *   - delivery_type    (opcional, max 30: pickup|courier|freight)
 *   - notes            (opcional, max 2000)
 */
class PurchaseOrdersImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['reference', 'supplier_name', 'warehouse_code', 'status', 'order_date', 'expected_delivery_date', 'currency_code', 'payment_terms_days', 'delivery_type', 'notes'],
            ['PO-2026-0001', 'Proveedor Demo SAC',   'WH-MAIN', 'draft',     '2026-05-18', '2026-05-25', 'PEN', '30', 'courier', 'OC de ejemplo'],
            ['',             'Distribuidora Acme',   'WH-LIMA', 'submitted', '2026-05-18', '',           'USD', '15', 'pickup',  ''],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'J';

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

                $help = [
                    'A1' => 'Codigo interno de la OC. Opcional — si lo dejas vacio se autogenera.',
                    'B1' => 'Nombre exacto de la empresa proveedora. Debe existir como Company con type supplier/both/partner.',
                    'C1' => 'Codigo o nombre del Warehouse destino. Debe existir y estar activo.',
                    'D1' => 'Estado inicial: draft | submitted | confirmed | partially_received | received | closed | cancelled.',
                    'E1' => 'Fecha de la orden (yyyy-mm-dd). Obligatorio.',
                    'F1' => 'Fecha estimada de entrega (yyyy-mm-dd). Opcional, debe ser posterior a order_date.',
                    'G1' => 'Codigo ISO 3 letras (PEN, USD, EUR...). Si vacio se usa el default del tenant.',
                    'H1' => 'Dias de pago al proveedor (Net 30 = 30).',
                    'I1' => 'Modalidad: pickup | courier | freight.',
                    'J1' => 'Notas o terminos especiales — texto libre hasta 2000 caracteres.',
                ];
                foreach ($help as $cell => $text) {
                    $comment = $sheet->getComment($cell);
                    $comment->setAuthor(__('imports.template_author'));
                    $comment->getText()->createTextRun($text);
                    $comment->setWidth('300pt');
                    $comment->setHeight('60pt');
                }
            },
        ];
    }
}
