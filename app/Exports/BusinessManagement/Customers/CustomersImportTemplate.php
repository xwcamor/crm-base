<?php

namespace App\Exports\BusinessManagement\Customers;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// @scaffold:remove-begin commercial-template-docblock
/**
 * Plantilla XLSX descargable para imports de Customers.
 *
 * Columnas:
 *   - name        (obligatorio, max 255)
 *   - cod         (opcional, max 50, unico por tenant)
 *   - country_iso (opcional, 2-3 chars, resuelve a country_id via iso_code)
 *   - is_active   (opcional, 1/0/true/false/si/no/activo/inactivo)
 *
 * No ponemos help-text como filas porque el importer las leeria como datos —
 * los tips van en cell comments.
 */
// @scaffold:remove-end
class CustomersImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        // @scaffold:remove-begin commercial-template-rows
        return [
            ['name', 'cod', 'country_iso', 'is_active'],
            ['Empresa Acme S.A.',   '20123456789', 'PE', '1'],
            ['Distribuidora Beta', '30598765432', 'AR', '1'],
            ['Servicios Gamma',    '',            '',   '0'],
        ];
        // @scaffold:remove-end
        // @scaffold:anchor generic-template-rows
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header SAP-blue
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Tooltips en headers (triangulos rojos, no pollutea datos).
                // @scaffold:anchor description-template-comment
                // @scaffold:remove-begin commercial-template-comments
                $commentCod = $sheet->getComment('B1');
                $commentCod->setAuthor(__('imports.template_author'));
                $commentCod->getText()->createTextRun(
                    'Codigo comercial (RUC, RFC, CUIT, NIT, etc). Opcional. Unico por workspace.'
                );
                $commentCod->setWidth('260pt');
                $commentCod->setHeight('60pt');

                $commentIso = $sheet->getComment('C1');
                $commentIso->setAuthor(__('imports.template_author'));
                $commentIso->getText()->createTextRun(
                    'Codigo ISO del pais (PE, AR, MX, US, etc). Opcional. Si no coincide con ningun pais activo, el customer queda sin pais asignado.'
                );
                $commentIso->setWidth('300pt');
                $commentIso->setHeight('80pt');
                // @scaffold:remove-end

                $commentActive = $sheet->getComment('D1');
                $commentActive->setAuthor(__('imports.template_author'));
                $commentActive->getText()->createTextRun(
                    __('imports.template_is_active_help')
                );
                $commentActive->setWidth('260pt');
                $commentActive->setHeight('60pt');
            },
        ];
    }
}
