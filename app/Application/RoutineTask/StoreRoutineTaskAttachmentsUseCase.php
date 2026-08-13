<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreRoutineTaskAttachmentsUseCase
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function execute(RoutineTask $task, array $files, ?int $uploaderEmployeeId): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('routine-tasks/'.$task->id, 'public');
            $mime = $file->getMimeType() ?? '';

            $task->attachments()->create([
                'uploaded_by_id' => $uploaderEmployeeId,
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => str_starts_with($mime, 'image/'),
            ]);
        }
    }

    public function deleteDiskFiles(RoutineTask $task): void
    {
        $task->loadMissing('attachments');

        foreach ($task->attachments as $attachment) {
            if ($attachment->path) {
                Storage::disk('public')->delete($attachment->path);
            }
        }
    }
}
