<?php

namespace App\Jobs\BusinessManagement\LeadSources;

use App\Exports\BusinessManagement\LeadSources\LeadSourcesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateLeadSourcesWordJob extends BaseLeadSourceExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $leadSources = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'lead_sources_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new LeadSourcesWord())->generate(
            leadSources:    $leadSources,
            filename:       $tempFile,
            options:        $opts,
            filtersSummary: $this->buildFiltersSummary(),
            generatedBy:    optional(\App\Models\User::find($this->userId))->name ?? '—',
        );

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        $path = 'downloads/' . $download->filename;
        Storage::disk($download->disk)->put($path, $content);

        $download->update(['path' => $path, 'status' => 'ready']);
    }
}
