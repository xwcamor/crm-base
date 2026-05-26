<?php

namespace App\Services\DownloadManagement;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class DownloadService
{
    /**
     * Expire and remove file from storage
     */
    public function expire(Download $download): void
    {
        Storage::disk($download->disk)->delete($download->path);

        $download->update([
            'status' => 'expired',
            'expires_at' => now(),
        ]);
    }
}