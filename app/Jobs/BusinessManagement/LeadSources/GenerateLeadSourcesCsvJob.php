<?php

namespace App\Jobs\BusinessManagement\LeadSources;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateLeadSourcesCsvJob extends BaseLeadSourceExportJob
{
    protected string $type      = 'csv';
    protected string $extension = 'csv';

    protected function executeExport(Download $download): void
    {
        $columns = $this->options['columns'] ?? ['id', 'name', 'category', 'sort_order', 'is_active', 'created_at'];

        $tempFile = tempnam(sys_get_temp_dir(), 'lead_sources_csv') . '.csv';
        $handle   = fopen($tempFile, 'w');

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $headings = [
                'id'          => __('lead_sources.id'),
                'name'        => __('lead_sources.name'),
                'description' => __('lead_sources.description'),
                'category'    => __('lead_sources.category'),
                'sort_order'  => __('lead_sources.sort_order'),
                'is_active'   => __('lead_sources.is_active'),
                'slug'        => 'Slug',
                'created_at'  => __('global.created_at'),
                'updated_at'  => __('global.updated_at'),
                'creator'     => __('global.created_by'),
            ];
            fputcsv($handle, array_map(fn ($k) => $headings[$k] ?? $k, $columns));

            $tz = $this->userTimezone;
            $this->buildQuery()->chunkById(1000, function ($leadSources) use ($handle, $columns, $tz) {
                foreach ($leadSources as $leadSource) {
                    $row = array_map(fn ($col) => match ($col) {
                        'id'          => $leadSource->id,
                        'name'        => $leadSource->name,
                        'description' => $leadSource->description ?? '',
                        'category'    => $leadSource->category ?? '',
                        'sort_order'  => $leadSource->sort_order,
                        'is_active'   => $leadSource->is_active ? '1' : '0',
                        'slug'        => $leadSource->slug,
                        'created_at'  => $leadSource->created_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'updated_at'  => $leadSource->updated_at?->copy()->setTimezone($tz)->format(\App\Support\Tz::DATETIME_FORMAT),
                        'creator'     => $leadSource->creator?->name ?? '',
                        default       => $leadSource->{$col} ?? '',
                    }, $columns);
                    fputcsv($handle, $row);
                }
            }, 'lead_sources.id', 'id');

            fclose($handle);
            $handle = null;

            $content = file_get_contents($tempFile);
            $path    = 'downloads/' . $download->filename;

            \DB::transaction(function () use ($download, $path, $content) {
                Storage::disk($download->disk)->put($path, $content);
                $download->update(['path' => $path, 'status' => 'ready']);
            });
        } finally {
            if (is_resource($handle)) @fclose($handle);
            if (file_exists($tempFile)) @unlink($tempFile);
        }
    }
}
