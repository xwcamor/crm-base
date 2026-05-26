<?php

namespace App\Jobs\BusinessManagement\ProductCategories;

use App\Exports\BusinessManagement\ProductCategories\ProductCategoriesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateProductCategoriesWordJob extends BaseProductCategoryExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $categories = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'product_categories_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new ProductCategoriesWord())->generate(
            categories:     $categories,
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
