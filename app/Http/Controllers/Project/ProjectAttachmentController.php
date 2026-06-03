<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\UpdateProjectAttachmentRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\ProjectAttachmentActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAttachmentController extends Controller
{
    public function file(Request $request, Project $project, ProjectAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $project);

        if ($attachment->project_id !== $project->id) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'category' => ['required', Rule::in(ProjectAttachmentCategory::values())],
            'files' => ['required', 'array', 'min:1', 'max:15'],
            'files.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv,json',
            ],
        ]);

        $account = $request->user();
        $category = ProjectAttachmentCategory::from($data['category']);

        foreach ($data['files'] as $file) {
            $path = $file->store("projects/{$project->id}/{$category->value}", 'public');
            $mime = $file->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/');

            $attachment = $project->attachments()->create([
                'category' => $category,
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => $isImage,
            ]);

            ProjectAttachmentActivityLogger::uploaded($attachment, $account);
        }

        return back()->with('success', 'Đã tải lên tài liệu.');
    }

    public function update(UpdateProjectAttachmentRequest $request, Project $project, ProjectAttachment $attachment): RedirectResponse
    {
        if ($attachment->project_id !== $project->id) {
            abort(404);
        }

        $account = $request->user();
        $data = $request->validated();

        if (array_key_exists('notes', $data) && $data['notes'] !== $attachment->notes) {
            $attachment->update([
                'notes' => $data['notes'],
                'updated_by_id' => $account->employee_id,
            ]);
            ProjectAttachmentActivityLogger::notesUpdated($attachment->fresh(), $account);
        }

        if ($request->hasFile('file')) {
            $this->authorize('manage', $project);

            $file = $request->file('file');
            $oldName = $attachment->original_name;
            Storage::disk('public')->delete($attachment->path);

            $path = $file->store("projects/{$project->id}/{$attachment->category->value}", 'public');
            $mime = $file->getMimeType() ?? '';

            $attachment->update([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => str_starts_with($mime, 'image/'),
                'updated_by_id' => $account->employee_id,
            ]);

            ProjectAttachmentActivityLogger::replaced($attachment->fresh(), $oldName, $account);
        }

        return back()->with('success', 'Đã cập nhật tài liệu.');
    }

    public function destroy(Request $request, Project $project, ProjectAttachment $attachment): RedirectResponse
    {
        $this->authorize('manage', $project);

        if ($attachment->project_id !== $project->id) {
            abort(404);
        }

        ProjectAttachmentActivityLogger::deleted($attachment, $request->user());
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Đã xoá tài liệu.');
    }
}
