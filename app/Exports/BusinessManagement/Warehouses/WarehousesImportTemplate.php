<?php

namespace App\Exports\BusinessManagement\Warehouses;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WarehousesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['name', 'description', 'is_active'],
            ['Ejemplo 1', 'Descripcion opcional del registro.', '1'],
            ['Ejemplo 2', '', '1'],
            ['Ejemplo 3', 'Otro detalle libre.', '0'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header SAP-blue
                $sheet->getStyle('A1:C1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A6ED1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '085CAF']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A', 'B', 'C'] as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Tooltips en headers (triangulos rojos, no pollutea datos).
                $commentDesc = $sheet->getComment('B1');
                $commentDesc->setAuthor(__('imports.template_author'));
                $commentDesc->getText()->createTextRun(
                    'Descripcion opcional del registro. Texto libre, hasta 1000 caracteres.'
                );
                $commentDesc->setWidth('260pt');
                $commentDesc->setHeight('60pt');
$commentActive = $sheet->getComment('C1');
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
