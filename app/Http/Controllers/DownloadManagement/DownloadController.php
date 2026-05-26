<?php

// Namespace
namespace App\Http\Controllers\DownloadManagement;

// Controllers
use App\Http\Controllers\Controller;

// Models
use App\Models\Download;

// Requests
use Illuminate\Http\Request;

// Services
use App\Services\DownloadManagement\DownloadService;

// Illuminates
use Illuminate\Support\Facades\Storage;

// Main class
class DownloadController extends Controller
{
    /**
     * Action Index
     * Display all downloads (admin view)
     */
    public function index(Request $request)
    {
        $downloads = Download::orderBy('created_at', 'desc')
            ->paginate(20)
            ->appends($request->all());

        return view('download_management.downloads.index', compact('downloads'));
    }

    /**
     * Action Show
     * Display a single download record (admin)
     */
    public function show($id)
    {
        $download = Download::with('user')->findOrFail($id);

        return view('download_management.downloads.show', compact('download'));
    }

    /**
     * Action Force Delete
     * Admin can expire a download and remove file
     */
    public function forceDelete($id, DownloadService $service)
    {
        $download = Download::findOrFail($id);

        $service->expire($download);

        return redirect()
            ->route('download_management.downloads.index')
            ->with('success', __('global.deleted_success'));
    }
}