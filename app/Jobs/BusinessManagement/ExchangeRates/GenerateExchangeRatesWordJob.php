<?php

namespace App\Jobs\BusinessManagement\ExchangeRates;

use App\Exports\BusinessManagement\ExchangeRates\ExchangeRatesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateExchangeRatesWordJob extends BaseExchangeRateExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $rates    = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'exchange_rates_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new ExchangeRatesWord())->generate(
            rates:          $rates,
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
