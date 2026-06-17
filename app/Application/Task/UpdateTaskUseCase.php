<?php

namespace App\Application\Task;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskProgress;
use App\Support\TaskSubtaskInheritance;
use App\Support\TaskTimeliness;

class UpdateTaskUseCase
{
    use Concerns\AppliesTaskStatusRules;

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
        TaskProgress::syncProgressFromStatus($data);

        if ($task->parent_id !== null) {
            unset(
                $data['parent_id'],
                $data['start_date'],
                $data['due_date'],
                $data['sprint_id'],
                $data['phase'],
                $data['assignee_id'],
            );
            $assigneeIds = null;
            if (array_key_exists('estimate_hours', $data) && $data['estimate_hours'] === null) {
                $data['estimate_hours'] = null;
            }
            $parent = $task->parent;
            if ($parent) {
                $data['start_date'] = $parent->start_date;
                $data['due_date'] = $parent->due_date;
            }
        }

        $data = $this->applyStatusTransitionRules($task, $data, $actor);
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
            $this->logStatusSideEffects($fresh, $previousStatus, $actor, true);
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $actor);
        }

        $changes = collect($fresh->getChanges())->except(['status', 'updated_at'])->all();
        if ($changes !== []) {
            TaskActivityLogger::updated($fresh, $actor, $changes);
            NotificationDispatcher::taskUpdated($fresh, $actor, $changes);
        }

        if ($dependencies !== null) {
            $depRelation = $task->dependencies();
            $depIdColumn = $depRelation->getRelated()->qualifyColumn('id');
            $beforeDeps = $depRelation->pluck($depIdColumn)->sort()->values()->all();
            $task->dependencies()->sync($dependencies);
            $afterDeps = collect($dependencies)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($beforeDeps !== $afterDeps) {
                TaskActivityLogger::dependenciesSynced($fresh, $actor);
            }
        }
        $assigneesChanged = false;
        if ($assigneeIds !== null) {
            $beforeAssignees = $task->assignees()->pluck('id')->sort()->values()->all();
            $task->assignees()->sync($assigneeIds);
            $primaryAssigneeId = $assigneeIds !== [] ? (int) $assigneeIds[0] : null;
            if ($task->assignee_id !== $primaryAssigneeId) {
                $task->update(['assignee_id' => $primaryAssigneeId]);
                $fresh = $task->fresh();
            }
            $afterAssignees = collect($assigneeIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
            $assigneesChanged = $beforeAssignees !== $afterAssignees;
            if ($assigneesChanged) {
                TaskActivityLogger::assigneesSynced($fresh, $actor);
            }
        }

        if ($fresh->parent_id === null && ($assigneesChanged || $fresh->wasChanged('assignee_id'))) {
            TaskSubtaskInheritance::syncSubtaskAssigneesFromParent($project, $fresh->fresh(['assignees', 'assignee']));
        }

        return $fresh;
    }
}
