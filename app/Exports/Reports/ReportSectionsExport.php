<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * ReportSectionsExport — export Excel multi-hoja para los reportes.
 *
 * Cada `section` se convierte en una hoja con sus columnas + filas.
 * Una sola clase generica vale para los 5 reportes — la estructura del
 * payload viene del ReportsController via los helpers buildXxxSections().
 */
class ReportSectionsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $sections,
        protected ?string $filtersSummary = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->sections as $section) {
            $sheets[] = new ReportSectionSheet(
                $section['title'],
                $section['columns'],
                $section['rows'],
            );
        }
        return $sheets;
    }
}

/**
 * Una hoja del Excel — extracted como clase aparte para WithMultipleSheets.
 */
class ReportSectionSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        protected string $title,
        protected array $columns,
        protected $rows,
    ) {}

    public function title(): string
    {
        // Excel limita titulos de hoja a 31 chars + caracteres seguros.
        return mb_substr(preg_replace('/[\\/\[\]:?*]/', '', $this->title), 0, 31);
    }

    public function headings(): array
    {
        return array_map(fn ($c) => $c['title'], $this->columns);
    }

    public function collection()
    {
        return collect($this->rows)->map(function ($r) {
            $out = [];
            foreach ($this->columns as $col) {
                $val = is_array($r) ? ($r[$col['key']] ?? null) : ($r->{$col['key']} ?? null);
                if (($col['type'] ?? null) === 'money' && $val !== null) {
                    $val = (float) $val;
                } elseif (($col['type'] ?? null) === 'pct' && $val !== null) {
                    $val = (float) $val;
                }
                $out[] = $val ?? '';
            }
            return $out;
        });
    }
}
