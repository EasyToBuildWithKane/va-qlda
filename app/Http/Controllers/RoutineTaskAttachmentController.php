<?php

namespace App\Http\Controllers;

use App\Application\RoutineTask\StoreRoutineTaskAttachmentsUseCase;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Domain\RoutineTask\Models\RoutineTaskAttachment;
use App\Http\Requests\RoutineTask\StoreRoutineTaskAttachmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoutineTaskAttachmentController extends Controller
{
    public function file(RoutineTask $routineTask, RoutineTaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $routineTask);
        abort_unless($attachment->routine_task_id === $routineTask->id, 404);

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }

    public function store(
        StoreRoutineTaskAttachmentRequest $request,
        RoutineTask $routineTask,
        StoreRoutineTaskAttachmentsUseCase $store,
    ): RedirectResponse {
        $store->execute(
            $routineTask,
            Arr::wrap($request->file('files') ?? []),
            $request->user()->employee_id ? (int) $request->user()->employee_id : null,
        );

        return back()->with('success', 'Đã tải lên tệp đính kèm.');
    }

    public function destroy(RoutineTask $routineTask, RoutineTaskAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $routineTask);
        abort_unless($attachment->routine_task_id === $routineTask->id, 404);

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return back()->with('success', 'Đã xoá tệp đính kèm.');
    }
}
