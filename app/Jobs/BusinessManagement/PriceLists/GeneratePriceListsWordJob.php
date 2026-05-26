<?php

namespace App\Jobs\BusinessManagement\PriceLists;

use App\Exports\BusinessManagement\PriceLists\PriceListsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePriceListsWordJob extends BasePriceListExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $priceLists = $this->buildQuery()->get();
        $tempFile   = tempnam(sys_get_temp_dir(), 'price_lists_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new PriceListsWord())->generate(
            priceLists:     $priceLists,
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
