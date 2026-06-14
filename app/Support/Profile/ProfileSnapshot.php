<?php

namespace App\Support\Profile;

use App\Models\Employee;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;

/**
 * Computes the performance-facing sections of a profile (stats strip, project
 * history, activity feed) entirely from data that already exists: tasks the
 * person is assigned, their worklogs, and their project memberships.
 *
 * Everything here is gated by EmployeePolicy::viewPerformance in the controller.
 */
class ProfileSnapshot
{
    /**
     * Headline counters for the stat strip.
     *
     * @return array<string, int|float>
     */
    public static function stats(Employee $employee): array
    {
        $today = Carbon::today()->toDateString();

        $agg = Task::query()
            ->where('assignee_id', $employee->id)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done', [TaskStatus::Done->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS in_progress', [TaskStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status <> ? AND due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) AS overdue', [TaskStatus::Done->value, $today])
            ->first();

        $total = (int) ($agg->total ?? 0);
        $done = (int) ($agg->done ?? 0);

        return [
            'tasks_total' => $total,
            'tasks_done' => $done,
            'tasks_in_progress' => (int) ($agg->in_progress ?? 0),
            'tasks_overdue' => (int) ($agg->overdue ?? 0),
            'completion_rate' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'worklog_hours' => round((float) Worklog::where('employee_id', $employee->id)->sum('hours'), 1),
            'projects_count' => $employee->relationLoaded('projects')
                ? $employee->projects->count()
                : $employee->projects()->count(),
        ];
    }

    /**
     * Full project history (active + past), newest first, with this person's
     * task throughput per project.
     *
     * @return list<array<string, mixed>>
     */
    public static function projectExperience(Employee $employee): array
    {
        $projects = $employee->relationLoaded('projects')
            ? $employee->projects
            : $employee->projects()->get();

        if ($projects->isEmpty()) {
            return [];
        }

        $counts = Task::query()
            ->where('assignee_id', $employee->id)
            ->whereIn('project_id', $projects->pluck('id'))
            ->selectRaw('project_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done', [TaskStatus::Done->value])
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        return $projects
            ->sortByDesc(fn ($p) => $p->getRelationValue('pivot')->joined_at ?? $p->getRelationValue('pivot')->created_at)
            ->map(function ($p) use ($counts) {
                $c = $counts->get($p->id);
                $pivot = $p->getRelationValue('pivot');

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'color' => $p->color,
                    'status' => self::enumFromRaw($p->getRawOriginal('status'), ProjectStatus::class),
                    'role' => $pivot->role,
                    'allocation' => $pivot->allocation,
                    'joined_at' => $pivot->joined_at
                        ? Carbon::parse($pivot->joined_at)->toDateString()
                        : null,
                    'is_active' => (bool) ($pivot->is_active ?? true),
                    'tasks_total' => (int) ($c->total ?? 0),
                    'tasks_done' => (int) ($c->done ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Unified recent activity from completed tasks, new assignments and worklogs.
     *
     * @return list<array<string, mixed>>
     */
    public static function activity(Employee $employee, int $limit = 15): array
    {
        $events = [];

        $completed = Task::with('project:id,name,code,color')
            ->where('assignee_id', $employee->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(10)
            ->get();

        foreach ($completed as $task) {
            $events[] = [
                'id' => 'task-done-'.$task->id,
                'type' => 'task_done',
                'icon' => 'done',
                'color' => 'emerald',
                'title' => 'Hoàn thành công việc',
                'subject' => $task->title,
                'project' => $task->project?->name,
                'at' => $task->completed_at?->toIso8601String(),
            ];
        }

        $assigned = Task::with('project:id,name,code,color')
            ->where('assignee_id', $employee->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($assigned as $task) {
            $events[] = [
                'id' => 'task-new-'.$task->id,
                'type' => 'task_assigned',
                'icon' => 'task',
                'color' => 'sky',
                'title' => 'Được giao công việc',
                'subject' => $task->title,
                'project' => $task->project?->name,
                'at' => $task->created_at?->toIso8601String(),
            ];
        }

        $logs = Worklog::with(['task:id,title,project_id', 'task.project:id,name'])
            ->where('employee_id', $employee->id)
            ->latest('date')
            ->limit(10)
            ->get();

        foreach ($logs as $log) {
            $events[] = [
                'id' => 'worklog-'.$log->id,
                'type' => 'worklog',
                'icon' => 'worklog',
                'color' => 'violet',
                'title' => 'Ghi nhận '.rtrim(rtrim((string) $log->hours, '0'), '.').'h',
                'subject' => $log->task?->title,
                'project' => $log->task?->project?->name,
                'at' => $log->date->toIso8601String(),
            ];
        }

        usort($events, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($events, 0, $limit);
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @return array{value:string, label:string, color:string|null}|null
     */
    private static function enumFromRaw(mixed $raw, string $enumClass): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (! is_subclass_of($enumClass, \BackedEnum::class)) {
            return null;
        }

        $enum = $enumClass::tryFrom($raw);

        return self::enum($enum);
    }

    /**
     * @return array{value:string, label:string, color:string|null}|null
     */
    private static function enum(?\BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return [
            'value' => $enum->value,
            'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value,
            'color' => method_exists($enum, 'color') ? $enum->color() : null,
        ];
    }
}
