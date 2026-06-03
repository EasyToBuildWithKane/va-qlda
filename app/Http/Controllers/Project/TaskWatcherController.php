<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskWatcherController extends Controller
{
    public function toggle(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $project);
        abort_unless($task->project_id === $project->id, 404);

        $employeeId = $request->user()->employee_id;
        abort_unless((bool) $employeeId, 403);

        $watching = $task->watchers()->where('employee_id', $employeeId)->exists();

        if ($watching) {
            $task->watchers()->detach($employeeId);
            TaskActivityLogger::watcherChanged($task, false, $request->user());
        } else {
            $task->watchers()->attach($employeeId);
            TaskActivityLogger::watcherChanged($task, true, $request->user());
        }

        return back()->with('success', $watching ? 'Đã ngừng theo dõi.' : 'Đang theo dõi công việc.');
    }
}
