<?php

namespace App\Support\WeeklyReport;

use App\Models\Task;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tính các KPI card hiển thị đầu Dashboard từ dữ liệu Sprint.
 *
 * @phpstan-type Kpi array<string, int|float|string|null>
 */
class WeeklyReportKpiBuilder
{
    /** @return Kpi */
    public function build(WeeklyReportContext $context): array
    {
        $tasks = $context->tasks;
        $rootTasks = $tasks->whereNull('parent_id');

        $total = $rootTasks->count();
        $done = $rootTasks->filter($this->isStatus(TaskStatus::Done))->count();
        $blocked = $tasks->filter($this->isStatus(TaskStatus::Blocked))->count();
        $overdue = $tasks->filter(fn (Task $t) => $this->isOverdue($t))->count();

        $openBlockers = $context->blockers;
        $criticalBugs = $openBlockers
            ->filter(fn ($b) => $b->severity === BlockerSeverity::Critical)
            ->count();

        $worklogHours = round((float) $context->worklogs->sum(fn ($w) => (float) $w->hours), 1);

        return [
            'sprint_progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'completed_tasks' => $done,
            'total_tasks' => $total,
            'remaining_tasks' => max($total - $done, 0),
            'overdue' => $overdue,
            'blocked' => $blocked,
            'open_issues' => $openBlockers->count(),
            'feedback' => $context->feedbacks->count(),
            'critical_bugs' => $criticalBugs,
            'worklog_hours' => $worklogHours,
            'team_velocity' => $this->velocity($rootTasks),
            'last_updated' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Velocity = % story point đã hoàn thành trên tổng đã commit (fallback: tỷ lệ task done).
     *
     * @param  Collection<int, Task>  $rootTasks
     */
    private function velocity(Collection $rootTasks): int
    {
        $committed = (float) $rootTasks->sum(fn (Task $t) => (float) ($t->story_points ?? 0));

        if ($committed <= 0) {
            $total = $rootTasks->count();
            $done = $rootTasks->filter($this->isStatus(TaskStatus::Done))->count();

            return $total > 0 ? (int) round($done / $total * 100) : 0;
        }

        $delivered = (float) $rootTasks
            ->filter($this->isStatus(TaskStatus::Done))
            ->sum(fn (Task $t) => (float) ($t->story_points ?? 0));

        return (int) round($delivered / $committed * 100);
    }

    private function isStatus(TaskStatus $status): callable
    {
        return fn (Task $t) => $t->status === $status;
    }

    private function isOverdue(Task $task): bool
    {
        return $task->due_date !== null
            && $task->due_date->isPast()
            && $task->status !== TaskStatus::Done;
    }
}
