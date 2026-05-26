<?php

namespace App\Jobs\BusinessManagement\ProductVariants;

use App\Exports\BusinessManagement\ProductVariants\ProductVariantsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateProductVariantsWordJob extends BaseProductVariantExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $variants = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'product_variants_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new ProductVariantsWord())->generate(
            variants:       $variants,
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
