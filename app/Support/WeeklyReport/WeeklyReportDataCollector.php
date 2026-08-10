<?php

namespace App\Support\WeeklyReport;

use App\Models\Blocker;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Worklog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gom toàn bộ dữ liệu nguồn cho một tuần báo cáo từ Sprint hiện tại:
 * Tasks, Worklogs, Activity, Test case (Blocker) và Phản hồi (Feedback).
 */
class WeeklyReportDataCollector
{
    public function collect(
        Project $project,
        ?Sprint $sprint,
        int $weekNumber,
        Carbon $weekStart,
        Carbon $weekEnd,
    ): WeeklyReportContext {
        $start = $weekStart->copy()->startOfDay();
        $end = $weekEnd->copy()->endOfDay();

        $tasks = $this->tasks($project, $sprint);
        $taskIds = $tasks->pluck('id')->all();

        return new WeeklyReportContext(
            project: $project,
            sprint: $sprint,
            weekNumber: $weekNumber,
            weekStart: $weekStart,
            weekEnd: $weekEnd,
            tasks: $tasks,
            worklogs: $this->worklogs($taskIds, $start, $end),
            activities: $this->activities($project, $start, $end),
            blockers: $this->blockers($project, $taskIds),
            feedbacks: $this->feedbacks($project, $start, $end),
        );
    }

    /** @return Collection<int, Task> */
    private function tasks(Project $project, ?Sprint $sprint): Collection
    {
        $query = Task::query()
            ->with(['assignee:id,full_name', 'epic:id,name'])
            ->where('project_id', $project->id);

        if ($sprint) {
            $query->where('sprint_id', $sprint->id);
        } else {
            $query->whereNull('sprint_id');
        }

        return $query->orderBy('order_column')->get();
    }

    /** @return Collection<int, Worklog> */
    private function worklogs(array $taskIds, Carbon $start, Carbon $end): Collection
    {
        if ($taskIds === []) {
            return collect();
        }

        return Worklog::query()
            ->with('employee:id,full_name')
            ->whereIn('task_id', $taskIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    /** @return Collection<int, ProjectActivity> */
    private function activities(Project $project, Carbon $start, Carbon $end): Collection
    {
        return ProjectActivity::query()
            ->where('project_id', $project->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->limit(60)
            ->get();
    }

    /**
     * Test case còn mở (bất kể thời điểm) + test case gắn task trong Sprint.
     *
     * @return Collection<int, Blocker>
     */
    private function blockers(Project $project, array $taskIds): Collection
    {
        return Blocker::query()
            ->with(['owner:id,full_name', 'task:id,title'])
            ->where('project_id', $project->id)
            ->where(function ($q) use ($taskIds) {
                $q->open();
                if ($taskIds !== []) {
                    $q->orWhereIn('task_id', $taskIds);
                }
            })
            ->orderByPriority()
            ->get();
    }

    /**
     * Phản hồi mới trong tuần hoặc còn đang mở.
     *
     * @return Collection<int, Feedback>
     */
    private function feedbacks(Project $project, Carbon $start, Carbon $end): Collection
    {
        return Feedback::query()
            ->where('project_id', $project->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])->orWhere(fn ($s) => $s->open());
            })
            ->latest()
            ->get();
    }
}
