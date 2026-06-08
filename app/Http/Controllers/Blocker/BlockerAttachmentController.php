<?php

namespace App\Http\Controllers\Blocker;

use App\Http\Controllers\Controller;
use App\Models\Blocker;
use App\Models\BlockerAttachment;
use App\Support\BlockerActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlockerAttachmentController extends Controller
{
    public function file(Request $request, Blocker $blocker, BlockerAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $blocker);

        if ($attachment->blocker_id !== $blocker->id) {
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

    public function store(Request $request, Blocker $blocker): RedirectResponse
    {
        $this->authorize('update', $blocker);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt',
            ],
        ]);

        $account = $request->user();

        foreach ($data['files'] as $file) {
            $path = $file->store('blockers/'.$blocker->id, 'public');
            $mime = $file->getMimeType() ?? '';
            $isImage = str_starts_with($mime, 'image/');

            $blocker->attachments()->create([
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => $isImage,
            ]);

            BlockerActivityLogger::attachmentAdded($blocker, $file->getClientOriginalName(), $account);
        }

        return back()->with('success', 'Đã tải lên file đính kèm.');
    }

    public function destroy(Blocker $blocker, BlockerAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $blocker);

        if ($attachment->blocker_id !== $blocker->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Đã xoá file đính kèm.');
    }
}
