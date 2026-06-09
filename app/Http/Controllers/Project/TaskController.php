<?php

namespace App\Http\Controllers\Project;

use App\Application\Task\BulkCreateTasksUseCase;
use App\Application\Task\CreateTaskUseCase;
use App\Application\Task\ImportTasksUseCase;
use App\Application\Task\PatchTaskUseCase;
use App\Application\Task\UpdateTaskUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\BulkStoreTaskRequest;
use App\Http\Requests\Project\ImportTaskRequest;
use App\Http\Requests\Project\StoreSubtaskRequest;
use App\Http\Requests\Project\StoreTaskRequest;
use App\Http\Requests\Project\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\NotificationService;
use App\Support\Enums\NotificationType;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\ProjectActivityLogger;
use App\Support\TaskActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly CreateTaskUseCase $createTask,
        private readonly BulkCreateTasksUseCase $bulkCreateTasks,
        private readonly ImportTasksUseCase $importTasks,
        private readonly UpdateTaskUseCase $updateTask,
        private readonly PatchTaskUseCase $patchTask,
    ) {}

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->createTask->execute($project, $request->validated(), $request->user());

        return back()->with('success', 'Đã thêm công việc.');
    }

    public function bulkStore(BulkStoreTaskRequest $request, Project $project): RedirectResponse
    {
        $created = $this->bulkCreateTasks->execute($project, $request->validated(), $request->user());

        return back()->with('success', "Đã tạo {$created} công việc.");
    }

    public function import(ImportTaskRequest $request, Project $project): RedirectResponse
    {
        $account = $request->user();
        $created = $this->importTasks->execute($project, $request->validated(), $account);

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            "Nhập {$created} công việc từ Excel",
            null,
            $project,
        );

        return back()->with('success', "Đã nhập {$created} công việc từ file.");
    }

    public function storeSubtask(StoreSubtaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        abort_if($task->parent_id !== null, 422, 'Chỉ thêm công việc con cho công việc cha (tối đa 1 cấp).');

        $data = $request->validated();

        $subtask = $project->tasks()->create([
            'parent_id' => $task->id,
            'title' => $data['title'],
            'status' => $data['status'] ?? TaskStatus::Todo->value,
            'priority' => $data['priority'] ?? TaskPriority::Medium->value,
            'estimate_hours' => $data['estimate_hours'] ?? null,
            'reporter_id' => $request->user()->employee_id,
            'sprint_id' => $task->sprint_id,
            'phase' => $task->phase?->value ?? $task->phase,
            'start_date' => $task->start_date,
            'due_date' => $task->due_date,
            'order_column' => (int) $project->tasks()->max('order_column') + 1,
        ]);

        TaskActivityLogger::subtaskAdded($task, $subtask, $request->user());

        return back()->with('success', 'Đã thêm công việc con.');
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? null;
        $assigneeIds = array_key_exists('assignee_ids', $data) ? $data['assignee_ids'] : null;
        unset($data['dependencies'], $data['assignee_ids']);

        $this->updateTask->execute($project, $task, $data, $dependencies, $assigneeIds, $request->user());

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
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'story_points' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999'],
            'epic_id' => ['sometimes', 'nullable', 'integer', Rule::exists('epics', 'id')->where('project_id', $project->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $result = $this->patchTask->execute($task, $validated, $request->user());

        return back()->with('success', $result['flash']);
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($task->project_id === $project->id, 404);

        $user = request()->user();
        ProjectActivityLogger::taskRemoved($project, $task, $user);
        $task->delete();

        return back()->with('success', 'Đã xoá công việc.');
    }
}
