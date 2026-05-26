<?php

namespace App\Jobs\AuthManagement\Users;

use App\Exports\AuthManagement\Users\UsersWord;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class GenerateUsersWordJob extends BaseUserExportJob
{
    protected string $type      = 'word';
    protected string $extension = 'docx';

    protected function executeExport(Download $download): void
    {
        $users    = $this->buildQuery()->get();
        $tempFile = tempnam(sys_get_temp_dir(), 'users_export') . '.docx';

        $opts = $this->options + ['timezone' => $this->userTimezone];

        (new UsersWord())->generate(
            users:          $users,
            filename:       $tempFile,
            options:        $opts,
            filtersSummary: $this->buildFiltersSummary(),
            generatedBy:    optional(\App\Models\User::withoutGlobalScopes()->find($this->userId))->name ?? '—',
        );

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        $path = 'downloads/' . $download->filename;
        Storage::disk($download->disk)->put($path, $content);

        $download->update(['path' => $path, 'status' => 'ready']);
    }
}
