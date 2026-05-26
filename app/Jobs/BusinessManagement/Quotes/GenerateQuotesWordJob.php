<?php

namespace App\Jobs\BusinessManagement\Quotes;

use App\Exports\BusinessManagement\Quotes\QuotesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateQuotesWordJob extends BaseQuoteExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $quotes = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'quotes_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new QuotesWord())->generate(
            quotes:      $quotes,
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
