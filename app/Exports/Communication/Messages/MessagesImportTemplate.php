<?php

namespace App\Exports\Communication\Messages;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MessagesImportTemplate implements FromArray, WithEvents
{
    public function array(): array
    {
        return [
            ['subject', 'body', 'audience_type', 'audience_id', 'allow_replies', 'is_active', 'expires_at'],
            ['Mantenimiento programado', 'El sistema estara en mantenimiento el sabado 23 de mayo de 02:00 a 04:00.', 'global', '', '0', '1', '2026-05-24 04:00:00'],
            ['Nueva funcionalidad para workspace ACME', 'Hemos habilitado los flujos automatizados.', 'tenant', '5', '1', '1', ''],
            ['Bienvenida personal', 'Bienvenido al equipo.', 'user', '42', '1', '1', ''],
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
                    'A1' => 'Asunto del mensaje (max 200). Debe ser unico entre los no-borrados.',
                    'B1' => 'Cuerpo del mensaje. Texto plano; el rich editor solo se usa desde la UI.',
                    'C1' => 'Tipo de audiencia: global | tenant | user.',
                    'D1' => 'Solo aplica si audience_type es tenant o user: id del tenant o user destino.',
                    'E1' => '1 si los destinatarios pueden responder; 0 si solo lectura.',
                    'F1' => __('imports.template_is_active_help'),
                    'G1' => 'Fecha de expiracion (YYYY-MM-DD HH:mm:ss). Vacio = sin fin.',
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
