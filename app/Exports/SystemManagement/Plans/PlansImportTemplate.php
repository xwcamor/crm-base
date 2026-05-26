<?php

namespace App\Exports\SystemManagement\Plans;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Template XLSX para imports de Plans. Incluye headers azules, tooltips
 * en cada columna y 3 filas de ejemplo. Clon de DiscountsImportTemplate.
 */
class PlansImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['slug', 'name', 'tagline', 'support_level', 'max_users', 'max_records_per_module', 'export_rate_limit', 'price_monthly', 'price_yearly', 'currency', 'is_active', 'is_public'],
            ['free',  'Free',  'Para probar el sistema',     'community', '1',  '100',  '1',  '0',    '0',     'USD', '1', '1'],
            ['solo',  'Solo',  'Para usuarios individuales', 'email',     '1',  '1000', '5',  '9.99', '99.99', 'USD', '1', '1'],
            ['pro',   'Pro',   'Para equipos pequeños',      'priority',  '-1', '-1',   '20', '49',   '490',   'USD', '1', '1'],
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
                    'A1' => 'Slug unico global (max 60, minusculas, numeros y guion bajo). Referenciado en tenants.plan.',
                    'B1' => 'Nombre visible del plan (max 100).',
                    'C1' => 'Subtitulo descriptivo opcional (max 200).',
                    'D1' => 'Nivel de soporte: community | email | priority.',
                    'E1' => 'Maximo de usuarios por workspace. -1 = ilimitado.',
                    'F1' => 'Maximo de registros por modulo. -1 = ilimitado.',
                    'G1' => 'Rate limit de exports por minuto (1-10000).',
                    'H1' => 'Precio facturado cada mes en la moneda elegida.',
                    'I1' => 'Precio facturado una vez al año.',
                    'J1' => 'Codigo ISO 4217 de 3 letras (USD, EUR, PEN).',
                    'K1' => __('imports.template_is_active_help'),
                    'L1' => 'Publico (1) = aparece en el modal "Ver planes". Privado (0) = oculto.',
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
