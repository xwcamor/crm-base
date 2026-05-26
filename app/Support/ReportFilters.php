<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ReportFilters — helper para resolver los filtros que llegan via query string
 * en los 5 reportes. Centraliza el parseo de date_preset -> [from, to] y la
 * extraccion de filtros opcionales (pipeline_id, owner_id, etc).
 *
 * Cada metodo del ReportsController hace:
 *   $f = ReportFilters::fromRequest($request);
 *   $q->whereBetween('created_at', [$f['date_from'], $f['date_to']]);
 *   if ($f['owner_id']) $q->where('owner_id', $f['owner_id']);
 */
class ReportFilters
{
    public static function fromRequest(Request $request, string $defaultPreset = 'last_30d'): array
    {
        $preset = $request->query('date_preset', $defaultPreset);
        [$from, $to] = self::resolvePreset(
            $preset,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        return [
            'date_preset'    => $preset,
            'date_from'      => $from,
            'date_to'        => $to,
            'pipeline_id'    => self::nullable($request->query('pipeline_id')),
            'owner_id'       => self::nullable($request->query('owner_id')),
            'lead_source_id' => self::nullable($request->query('lead_source_id')),
            'currency_code'  => self::nullable($request->query('currency_code')),
            'activity_type'  => self::nullable($request->query('activity_type')),
        ];
    }

    /**
     * Convierte un preset (today, last_7d, etc) a un par [Carbon $from, Carbon $to].
     * Si preset = 'custom', usa los from/to explicitos del request.
     * Si preset = 'all', devuelve [null, null] (no aplicar filtro de fecha).
     */
    public static function resolvePreset(string $preset, ?string $customFrom, ?string $customTo): array
    {
        $now = Carbon::now();
        return match ($preset) {
            'today'        => [$now->copy()->startOfDay(),  $now->copy()->endOfDay()],
            'last_7d'      => [$now->copy()->subDays(7)->startOfDay(),  $now->copy()->endOfDay()],
            'last_30d'     => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'last_90d'     => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
            'this_month'   => [$now->copy()->startOfMonth(),    $now->copy()->endOfMonth()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(),  $now->copy()->endOfQuarter()],
            'this_year'    => [$now->copy()->startOfYear(),     $now->copy()->endOfYear()],
            'last_year'    => [$now->copy()->subYear()->startOfYear(),  $now->copy()->subYear()->endOfYear()],
            'all'          => [null, null],
            'custom'       => [
                $customFrom ? Carbon::parse($customFrom)->startOfDay() : null,
                $customTo   ? Carbon::parse($customTo)->endOfDay()     : null,
            ],
            default => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private static function nullable($v)
    {
        return ($v === '' || $v === null) ? null : $v;
    }
}
