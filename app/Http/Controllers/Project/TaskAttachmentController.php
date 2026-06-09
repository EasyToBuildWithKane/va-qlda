<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Support\TaskActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $project);
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,mp4,mov',
            ],
        ]);

        $account = $request->user();

        foreach ($data['files'] as $file) {
            $path = $file->store('tasks/'.$task->id, 'public');
            $mime = $file->getMimeType() ?? '';

            $task->attachments()->create([
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => str_starts_with($mime, 'image/'),
                'version' => 1,
            ]);

            TaskActivityLogger::attachmentAdded($task, $file->getClientOriginalName(), $account);
        }

        return back()->with('success', 'Đã tải lên file đính kèm.');
    }

    public function destroy(Project $project, Task $task, TaskAttachment $attachment): RedirectResponse
    {
        $this->authorize('contribute', $project);
        abort_unless($task->project_id === $project->id && $attachment->task_id === $task->id, 404);

        $name = $attachment->original_name;
        Storage::disk('public')->delete($attachment->path);
        TaskActivityLogger::attachmentRemoved($task, $name, request()->user());
        $attachment->delete();

        return back()->with('success', 'Đã xoá file đính kèm.');
    }
}
