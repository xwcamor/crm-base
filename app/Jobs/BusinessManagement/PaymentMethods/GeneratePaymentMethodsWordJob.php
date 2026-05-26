<?php

namespace App\Jobs\BusinessManagement\PaymentMethods;

use App\Exports\BusinessManagement\PaymentMethods\PaymentMethodsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GeneratePaymentMethodsWordJob extends BasePaymentMethodExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $methods  = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'payment_methods_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new PaymentMethodsWord())->generate(
            methods:        $methods,
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
