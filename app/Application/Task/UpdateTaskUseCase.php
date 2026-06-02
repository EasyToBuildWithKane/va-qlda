<?php

namespace App\Application\Task;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskTimeliness;

class UpdateTaskUseCase
{
    /**
     * @param  array<string, mixed>  $data  Validated from UpdateTaskRequest (without dependencies / assignee_ids)
     * @param  array<int>|null  $dependencies
     * @param  array<int>|null  $assigneeIds
     */
    public function execute(
        Project $project,
        Task $task,
        array $data,
        ?array $dependencies,
        ?array $assigneeIds,
        SystemAccount $actor,
    ): Task {
        $previousStatus = $task->status->value;

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
                $actor,
            );
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $actor);
        }

        $changes = collect($fresh->getChanges())->except(['status', 'updated_at'])->all();
        if ($changes !== []) {
            TaskActivityLogger::updated($fresh, $actor, $changes);
            NotificationDispatcher::taskUpdated($fresh, $actor, $changes);
        }

        if ($dependencies !== null) {
            $task->dependencies()->sync($dependencies);
        }
        if ($assigneeIds !== null) {
            $task->assignees()->sync($assigneeIds);
        }

        return $fresh;
    }
}
