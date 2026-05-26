<?php

namespace App\Jobs\BusinessManagement\StockTakes;

use App\Exports\BusinessManagement\StockTakes\StockTakesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateStockTakesWordJob extends BaseStockTakeExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $takes    = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'stock_takes_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new StockTakesWord())->generate(
            takes:          $takes,
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
