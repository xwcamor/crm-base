<?php

namespace App\Jobs\SystemManagement\Plans;

use App\Exports\SystemManagement\Plans\PlansWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePlansWordJob extends BasePlanExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $plans    = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'plans_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new PlansWord())->generate(
            plans:          $plans,
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
