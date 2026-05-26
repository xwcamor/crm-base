<?php

namespace App\Jobs\BusinessManagement\Invoices;

use App\Exports\BusinessManagement\Invoices\InvoicesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicesWordJob extends BaseInvoiceExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $invoices = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'invoices_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new InvoicesWord())->generate(
            invoices:      $invoices,
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
