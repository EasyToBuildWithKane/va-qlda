<?php

namespace App\Http\Controllers\Project;

use App\Application\Project\LogWorkUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreWorklogRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\TaskActivityLogger;
use Illuminate\Http\RedirectResponse;

class WorklogController extends Controller
{
    public function store(StoreWorklogRequest $request, Project $project, Task $task, LogWorkUseCase $useCase): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validated();
        $useCase->execute($task, $data);

        TaskActivityLogger::log(
            $task,
            'worklog',
            'Ghi nhận '.$data['hours'].'h làm việc',
            ['date' => $data['date'] ?? null, 'hours' => $data['hours']],
            $request->user()->employee_id,
        );

        return back()->with('success', 'Đã ghi nhận giờ làm.');
    }

    public function destroy(Project $project, Task $task, Worklog $worklog): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($task->project_id === $project->id && $worklog->task_id === $task->id, 404);

        $worklog->delete();

        return back()->with('success', 'Đã xoá bản ghi giờ làm.');
    }
}
