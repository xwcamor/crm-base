<?php

namespace App\Jobs\Crm\Contacts;

use App\Exports\Crm\Contacts\ContactsWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateContactsWordJob extends BaseContactExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $contacts = $this->buildQuery()->get();
        $tempFile  = tempnam(sys_get_temp_dir(), 'contacts_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new ContactsWord())->generate(
            contacts:      $contacts,
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
