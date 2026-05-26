<?php

namespace App\Jobs\Crm\Pipelines;

use App\Exports\Crm\Pipelines\PipelinesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePipelinesWordJob extends BasePipelineExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $pipelines = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'pipelines_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new PipelinesWord())->generate(
            pipelines:      $pipelines,
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
