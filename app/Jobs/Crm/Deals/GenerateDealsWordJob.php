<?php

namespace App\Jobs\Crm\Deals;

use App\Exports\Crm\Deals\DealsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateDealsWordJob extends BaseDealExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $deals = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'deals_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new DealsWord())->generate(
            deals:      $deals,
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
