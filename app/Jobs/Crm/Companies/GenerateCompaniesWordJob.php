<?php

namespace App\Jobs\Crm\Companies;

use App\Exports\Crm\Companies\CompaniesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateCompaniesWordJob extends BaseCompanyExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $companies = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'companies_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new CompaniesWord())->generate(
            companies:      $companies,
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
