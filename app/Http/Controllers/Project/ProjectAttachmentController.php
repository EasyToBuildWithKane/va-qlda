<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectAttachmentRequest;
use App\Http\Requests\Project\UpdateProjectAttachmentRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Support\ProjectAttachmentActivityLogger;
use App\Support\ProjectAttachmentExternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAttachmentController extends Controller
{
    public function file(Request $request, Project $project, ProjectAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $project);

        if ($attachment->project_id !== $project->id) {
            abort(404);
        }

        if ($attachment->isFolder()) {
            abort(404);
        }

        if ($attachment->isExternalLink()) {
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

    public function store(StoreProjectAttachmentRequest $request, Project $project): RedirectResponse
    {
        $account = $request->user();
        $data = $request->validated();
        $category = $data['category'];
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        if ($request->boolean('is_folder')) {
            $folderName = trim((string) ($data['folder_name'] ?? $data['title'] ?? ''));

            $attachment = $project->attachments()->create([
                'category' => $category,
                'parent_id' => $parentId,
                'is_folder' => true,
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $folderName,
                'path' => '',
                'mime_type' => null,
                'size' => 0,
                'is_image' => false,
            ]);

            ProjectAttachmentActivityLogger::folderCreated($attachment, $account);

            return back()->with('success', 'Đã tạo thư mục.');
        }

        $externalUrl = trim((string) ($data['external_url'] ?? ''));
        if ($externalUrl !== '') {
            $parsed = ProjectAttachmentExternalUrl::parse($externalUrl);
            if ($parsed === null) {
                return back()->withErrors(['external_url' => 'Link không hợp lệ.']);
            }

            $title = trim((string) ($data['title'] ?? ''));
            $originalName = $title !== '' ? $title : $parsed['default_title'];

            $attachment = $project->attachments()->create([
                'category' => $category,
                'parent_id' => $parentId,
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $originalName,
                'path' => '',
                'external_url' => $parsed['view_url'],
                'mime_type' => $parsed['mime_type'],
                'size' => 0,
                'is_image' => false,
            ]);

            ProjectAttachmentActivityLogger::linkAdded($attachment, $account);

            return back()->with('success', 'Đã thêm link tài liệu.');
        }

        foreach ($request->file('files', []) as $file) {
            $path = $file->store("projects/{$project->id}/{$category}", 'public');
            $mime = $file->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/');

            $attachment = $project->attachments()->create([
                'category' => $category,
                'parent_id' => $parentId,
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
            if ($attachment->isFolder()) {
                return back()->withErrors(['notes' => 'Thư mục không hỗ trợ ghi chú.']);
            }

            $attachment->update([
                'notes' => $data['notes'],
                'updated_by_id' => $account->employee_id,
            ]);
            ProjectAttachmentActivityLogger::notesUpdated($attachment->fresh(), $account);
        }

        if (array_key_exists('external_url', $data) && $attachment->isExternalLink()) {
            $parsed = ProjectAttachmentExternalUrl::parse((string) $data['external_url']);
            if ($parsed === null) {
                return back()->withErrors(['external_url' => 'Link không hợp lệ.']);
            }

            $updates = [
                'external_url' => $parsed['view_url'],
                'mime_type' => $parsed['mime_type'],
                'updated_by_id' => $account->employee_id,
            ];

            $title = trim((string) ($data['title'] ?? ''));
            if ($title !== '') {
                $updates['original_name'] = $title;
            }

            $attachment->update($updates);
            ProjectAttachmentActivityLogger::linkUpdated($attachment->fresh(), $account);
        }

        if ($attachment->isFolder() && array_key_exists('title', $data)) {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title !== '' && $title !== $attachment->original_name) {
                $oldName = $attachment->original_name;
                $attachment->update([
                    'original_name' => $title,
                    'updated_by_id' => $account->employee_id,
                ]);
                ProjectAttachmentActivityLogger::folderRenamed($attachment->fresh(), $oldName, $account);
            }
        }

        if ($request->hasFile('file')) {
            if ($attachment->isFolder()) {
                return back()->withErrors(['file' => 'Không thể thay thế file cho thư mục.']);
            }

            if ($attachment->isExternalLink()) {
                return back()->withErrors(['file' => 'Không thể thay thế file cho bản ghi link ngoài.']);
            }

            $this->authorize('manage', $project);

            $file = $request->file('file');
            $oldName = $attachment->original_name;
            if ($attachment->path !== '') {
                Storage::disk('public')->delete($attachment->path);
            }

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

        $this->deleteAttachmentTree($attachment, $request->user());

        return back()->with('success', $attachment->isFolder() ? 'Đã xoá thư mục và nội dung bên trong.' : 'Đã xoá tài liệu.');
    }

    private function deleteAttachmentTree(ProjectAttachment $attachment, ?\App\Models\SystemAccount $account): void
    {
        if ($attachment->isFolder()) {
            $attachment->loadMissing('children');
            foreach ($attachment->children as $child) {
                $this->deleteAttachmentTree($child, $account);
            }
        } else {
            if (! $attachment->isExternalLink() && $attachment->path !== '') {
                Storage::disk('public')->delete($attachment->path);
            }
        }

        ProjectAttachmentActivityLogger::deleted($attachment, $account);
        $attachment->delete();
    }
}
