<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;

class TaskSubtaskInheritance
{
    /**
     * @return list<int>
     */
    public static function assigneeIds(Task $task): array
    {
        $task->loadMissing('assignees');

        $ids = $task->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids !== []) {
            return $ids;
        }

        return $task->assignee_id ? [(int) $task->assignee_id] : [];
    }

    /**
     * @param  list<int>  $ids
     */
    public static function applyAssignees(Task $task, array $ids): void
    {
        $task->assignees()->sync($ids);
        $task->update(['assignee_id' => $ids[0] ?? null]);
    }

    public static function copyAssigneesFromParent(Task $parent, Task $subtask): void
    {
        self::applyAssignees($subtask, self::assigneeIds($parent));
    }

    public static function syncSubtaskAssigneesFromParent(Project $project, Task $parent): void
    {
        if ($parent->parent_id !== null) {
            return;
        }

        $ids = self::assigneeIds($parent);
        $project->tasks()
            ->where('parent_id', $parent->id)
            ->each(fn (Task $child) => self::applyAssignees($child, $ids));
    }
}
