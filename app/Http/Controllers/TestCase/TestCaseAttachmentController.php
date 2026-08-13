<?php

namespace App\Http\Controllers\TestCase;

use App\Http\Controllers\Controller;
use App\Models\TestCase;
use App\Models\TestCaseAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestCaseAttachmentController extends Controller
{
    public function file(Request $request, TestCase $testCase, TestCaseAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $testCase);

        if ($attachment->test_case_id !== $testCase->id) {
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

    public function store(Request $request, TestCase $testCase): RedirectResponse
    {
        $this->authorize('update', $testCase);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt',
            ],
        ], [
            'files.required' => 'Vui lòng chọn ít nhất một file.',
            'files.*.max' => 'Mỗi file tối đa 10MB.',
            'files.*.mimes' => 'Định dạng file không được hỗ trợ.',
        ]);

        $account = $request->user();

        foreach ($data['files'] as $file) {
            $path = $file->store('test-cases/'.$testCase->id, 'public');
            $mime = $file->getMimeType() ?? '';

            $testCase->attachments()->create([
                'uploaded_by_id' => $account->employee_id,
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => str_starts_with($mime, 'image/'),
            ]);
        }

        return back()->with('success', 'Đã tải lên file đính kèm.');
    }

    public function destroy(TestCase $testCase, TestCaseAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $testCase);

        if ($attachment->test_case_id !== $testCase->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Đã xoá file đính kèm.');
    }
}
