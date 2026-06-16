<?php

namespace App\Support\Performance;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Truy vấn task thống nhất cho module Hiệu suất — gán qua assignee_id hoặc task_assignees,
 * và kỳ sprint = membership sprint_id (không lọc chéo ngày quá hẹp).
 */
class PerformanceTaskScope
{
    /**
     * @param  Collection<int, int>  $employeeIds
     * @return Builder<Task>
     */
    public static function forEmployees(Collection $employeeIds, PerformanceFilter $filter, ?Carbon $start = null, ?Carbon $end = null): Builder
    {
        $start ??= $filter->start;
        $end ??= $filter->end;

        $query = Task::query()
            ->whereNull('parent_id')
            ->where(function (Builder $q) use ($employeeIds) {
                $q->whereIn('assignee_id', $employeeIds)
                    ->orWhereHas(
                        'assignees',
                        fn (Builder $a) => $a->whereIn('employees.id', $employeeIds),
                    );
            });

        if ($filter->sprintId) {
            $query->where('sprint_id', $filter->sprintId);
        } else {
            $query->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereBetween('work_started_at', [$start, $end]);
            });
        }

        if ($filter->projectId) {
            $query->where('project_id', $filter->projectId);
        }
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }

        return $query;
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return Builder<Task>
     */
    public static function forEmployee(int $employeeId, PerformanceFilter $filter): Builder
    {
        return self::forEmployees(collect([$employeeId]), $filter);
    }

    /**
     * Task thuộc bucket cam kết (kế hoạch) — sprint: gồm task không deadline trong tuần mở sprint.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    public static function plannedInBucket(Collection $tasks, Carbon $start, Carbon $end, PerformanceFilter $filter): Collection
    {
        return $tasks->filter(function (Task $t) use ($start, $end, $filter) {
            $due = $t->due_date && $t->due_date->betweenIncluded($start, $end);
            $started = $t->work_started_at && $t->work_started_at->betweenIncluded($start, $end);

            if ($due || $started) {
                return true;
            }

            if ($filter->sprintId && ! $t->due_date && ! $t->work_started_at) {
                return $start->lte($filter->start) && $end->gte($filter->start);
            }

            return false;
        });
    }

    /**
     * @return list<int>
     */
    public static function assigneeIds(Task $task): array
    {
        if ($task->assignee_id) {
            return [(int) $task->assignee_id];
        }

        if ($task->relationLoaded('assignees')) {
            return $task->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Collection<int, Task>>
     */
    public static function groupByAssignee(Collection $tasks, Collection $employeeIds): Collection
    {
        $grouped = collect();

        foreach ($tasks as $task) {
            foreach (self::assigneeIds($task) as $employeeId) {
                if (! $employeeIds->contains($employeeId)) {
                    continue;
                }
                if (! $grouped->has($employeeId)) {
                    $grouped->put($employeeId, collect());
                }
                $grouped->get($employeeId)->push($task);
            }
        }

        return $grouped;
    }
}
