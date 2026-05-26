<?php

namespace App\Jobs\SystemManagement\SystemModules;

use App\Exports\SystemManagement\SystemModules\SystemModulesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateSystemModulesWordJob extends BaseSystemModuleExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $system_modules  = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'system_modules_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new SystemModulesWord())->generate(
            system_modules:        $system_modules,
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
