<?php

namespace App\Jobs\BusinessManagement\TaxClasses;

use App\Exports\BusinessManagement\TaxClasses\TaxClassesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateTaxClassesWordJob extends BaseTaxClassExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $tax_classes = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'tax_classes_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new TaxClassesWord())->generate(
            tax_classes:      $tax_classes,
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
