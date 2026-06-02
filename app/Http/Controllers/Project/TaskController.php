<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreTaskRequest;
use App\Http\Requests\Project\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? [];
        unset($data['dependencies']);

        $task = $project->tasks()->create([
            ...$data,
            'reporter_id' => $data['reporter_id'] ?? $request->user()->employee_id,
            'order_column' => (int) $project->tasks()->max('order_column') + 1,
        ]);

        if ($dependencies) {
            $task->dependencies()->sync($dependencies);
        }

        return back()->with('success', 'Đã thêm công việc.');
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? null;
        unset($data['dependencies']);

        $task->update($data);

        if ($dependencies !== null) {
            $task->dependencies()->sync($dependencies);
        }

        return back()->with('success', 'Đã cập nhật công việc.');
    }

    /**
     * Lightweight status/progress change used by the Kanban board and Gantt.
     */
    public function updateStatus(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $project);
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(TaskStatus::values())],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'order_column' => ['sometimes', 'integer', 'min:0'],
            'sprint_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $project->id)],
        ]);

        // Reaching "done" implies full progress; leaving it drops back if at 100.
        if (($validated['status'] ?? null) === TaskStatus::Done->value && ! isset($validated['progress'])) {
            $validated['progress'] = 100;
        }

        $task->update($validated);

        return back();
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($task->project_id === $project->id, 404);

        $task->delete();

        return back()->with('success', 'Đã xoá công việc.');
    }
}
