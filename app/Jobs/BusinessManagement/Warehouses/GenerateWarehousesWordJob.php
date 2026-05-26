<?php

namespace App\Jobs\BusinessManagement\Warehouses;

use App\Exports\BusinessManagement\Warehouses\WarehousesWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateWarehousesWordJob extends BaseWarehouseExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $warehouses = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'warehouses_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new WarehousesWord())->generate(
            warehouses:      $warehouses,
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
