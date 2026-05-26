<?php

namespace App\Services\Crm;

use App\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ActivityService
{
    public function create(array $data, ?UploadedFile $attachment = null): Activity
    {
        if ($attachment) {
            $data['attachment_path'] = $attachment->store('activities', 'public');
            $data['attachment_name'] = $attachment->getClientOriginalName();
        }

        $a = new Activity($data);
        $a->actor_user_id = auth()->id();
        $a->created_by    = auth()->id();
        $a->save();
        return $a;
    }

    public function update(Activity $activity, array $data, ?UploadedFile $attachment = null): Activity
    {
        if ($attachment) {
            // Reemplazar adjunto previo si lo habia
            if ($activity->attachment_path) {
                Storage::disk('public')->delete($activity->attachment_path);
            }
            $data['attachment_path'] = $attachment->store('activities', 'public');
            $data['attachment_name'] = $attachment->getClientOriginalName();
        }

        $activity->update($data);
        return $activity;
    }

    public function delete(Activity $activity): void
    {
        // No borramos el archivo del disco — soft-delete reversible.
        $activity->deleted_by = auth()->id();
        $activity->saveQuietly();
        $activity->delete();
    }

    public function markComplete(Activity $activity): Activity
    {
        $activity->completed_at = now();
        $activity->save();
        return $activity;
    }

    public function markPending(Activity $activity): Activity
    {
        $activity->completed_at = null;
        $activity->save();
        return $activity;
    }
}
