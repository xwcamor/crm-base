<?php

namespace App\Jobs\BusinessManagement\Payments;

use App\Exports\BusinessManagement\Payments\PaymentsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePaymentsWordJob extends BasePaymentExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $payments = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'payments_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new PaymentsWord())->generate(
            payments:      $payments,
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
