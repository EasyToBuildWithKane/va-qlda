<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\BulkStoreTaskRequest;
use App\Http\Requests\Project\StoreSubtaskRequest;
use App\Http\Requests\Project\StoreTaskRequest;
use App\Http\Requests\Project\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskTimeliness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? [];
        $assigneeIds = $data['assignee_ids'] ?? [];
        unset($data['dependencies'], $data['assignee_ids']);

        $task = $project->tasks()->create([
            ...$data,
            'reporter_id' => $data['reporter_id'] ?? $request->user()->employee_id,
            'order_column' => (int) $project->tasks()->max('order_column') + 1,
        ]);

        if ($dependencies) {
            $task->dependencies()->sync($dependencies);
        }
        if ($assigneeIds) {
            $task->assignees()->sync($assigneeIds);
        }

        TaskTimeliness::syncWorkStartedAt($task->fresh());
        TaskActivityLogger::created($task->fresh(), $request->user());
        NotificationDispatcher::taskCreated($task->fresh(['project']), $request->user());

        return back()->with('success', 'Đã thêm công việc.');
    }

    public function bulkStore(BulkStoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $defaults = $data['defaults'] ?? [];
        $account = $request->user();
        $created = 0;
        $orderBase = (int) $project->tasks()->max('order_column');

        $assigneeIds = $defaults['assignee_ids'] ?? [];

        DB::transaction(function () use ($data, $defaults, $assigneeIds, $project, $account, &$created, $orderBase) {
            foreach ($data['rows'] as $i => $row) {
                $payload = [
                    'title' => $row['title'],
                    'status' => $defaults['status'] ?? TaskStatus::Todo->value,
                    'priority' => $defaults['priority'] ?? TaskPriority::Medium->value,
                    'phase' => $defaults['phase'] ?? TaskPhase::Development->value,
                    'sprint_id' => $defaults['sprint_id'] ?? null,
                    'assignee_id' => $defaults['assignee_id'] ?? null,
                    'reviewer_id' => $defaults['reviewer_id'] ?? null,
                    'reporter_id' => $defaults['reporter_id'] ?? $account->employee_id,
                    'progress' => 0,
                    'order_column' => $orderBase + $i + 1,
                ];

                $task = $project->tasks()->create($payload);

                if ($assigneeIds !== []) {
                    $task->assignees()->sync($assigneeIds);
                }

                TaskTimeliness::syncWorkStartedAt($task->fresh());
                TaskActivityLogger::created($task->fresh(), $account);
                NotificationDispatcher::taskCreated($task->fresh(['project']), $account);
                $created++;
            }
        });

        return back()->with('success', "Đã tạo {$created} công việc.");
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

        $previousStatus = $task->status->value;
        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? null;
        $assigneeIds = array_key_exists('assignee_ids', $data) ? $data['assignee_ids'] : null;
        unset($data['dependencies'], $data['assignee_ids']);

        if ($task->parent_id !== null) {
            unset($data['parent_id'], $data['start_date'], $data['due_date'], $data['sprint_id'], $data['phase']);
            if (array_key_exists('estimate_hours', $data) && $data['estimate_hours'] === null) {
                $data['estimate_hours'] = null;
            }
            $parent = $task->parent;
            if ($parent) {
                $data['start_date'] = $parent->start_date;
                $data['due_date'] = $parent->due_date;
            }
        }

        $task->update($data);
        $fresh = $task->fresh();
        TaskTimeliness::syncWorkStartedAt($fresh, $previousStatus);

        if ($fresh->parent_id === null && $fresh->wasChanged(['start_date', 'due_date'])) {
            $project->tasks()->where('parent_id', $fresh->id)->update([
                'start_date' => $fresh->start_date,
                'due_date' => $fresh->due_date,
            ]);
        }

        if ($fresh->wasChanged('status')) {
            TaskActivityLogger::statusChanged(
                $fresh,
                $previousStatus,
                $fresh->status->value,
                $request->user(),
            );
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $request->user());
        }
        $changes = collect($fresh->getChanges())->except(['status', 'updated_at'])->all();
        if ($changes !== []) {
            TaskActivityLogger::updated($fresh, $request->user(), $changes);
            NotificationDispatcher::taskUpdated($fresh, $request->user(), $changes);
        }

        if ($dependencies !== null) {
            $task->dependencies()->sync($dependencies);
        }
        if ($assigneeIds !== null) {
            $task->assignees()->sync($assigneeIds);
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
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'story_points' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999'],
            'epic_id' => ['sometimes', 'nullable', 'integer', Rule::exists('epics', 'id')->where('project_id', $project->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $newStatus = $validated['status'] ?? $task->status->value;

        // Trạng thái → tiến độ (phản ánh ngay trên bảng sprint / kanban).
        if ($newStatus === TaskStatus::Done->value && ! isset($validated['progress'])) {
            $validated['progress'] = 100;
        } elseif ($newStatus === TaskStatus::InProgress->value && $task->progress < 1) {
            $validated['progress'] = max(1, (int) $task->progress);
        } elseif ($newStatus === TaskStatus::InReview->value && $task->progress < 50) {
            $validated['progress'] = max(50, (int) $task->progress);
        } elseif ($newStatus === TaskStatus::Todo->value && $task->progress >= 100) {
            $validated['progress'] = 0;
        }

        $previousStatus = $task->status->value;
        $task->update($validated);
        $fresh = $task->fresh();
        TaskTimeliness::syncWorkStartedAt($fresh, $previousStatus);

        if ($fresh->wasChanged('status')) {
            TaskActivityLogger::statusChanged(
                $fresh,
                $previousStatus,
                $fresh->status->value,
                $request->user(),
            );
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $request->user());
        } elseif ($fresh->getChanges() !== []) {
            $chg = collect($fresh->getChanges())->except(['updated_at'])->all();
            TaskActivityLogger::updated($fresh, $request->user(), $chg);
            NotificationDispatcher::taskUpdated($fresh, $request->user(), $chg);
        }

        $flash = match ($newStatus) {
            TaskStatus::InProgress->value => 'Đã bắt đầu làm — SLA giờ ước tính đang chạy.',
            TaskStatus::InReview->value => 'Đã chuyển sang chờ duyệt.',
            TaskStatus::Done->value => 'Đã hoàn thành — tiến độ 100%.',
            TaskStatus::Blocked->value => 'Đã đánh dấu bị chặn.',
            TaskStatus::Todo->value => 'Đã chuyển về cần làm.',
            default => 'Đã cập nhật trạng thái.',
        };

        return back()->with('success', $flash);
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($task->project_id === $project->id, 404);

        $task->delete();

        return back()->with('success', 'Đã xoá công việc.');
    }
}
