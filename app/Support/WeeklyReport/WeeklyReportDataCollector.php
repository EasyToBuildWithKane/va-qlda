<?php

namespace App\Support\WeeklyReport;

use App\Models\Blocker;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gom dữ liệu nguồn theo khoảng ngày trên toàn dự án (không kẹp Sprint):
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

        $worklogs = $this->worklogs($project, $start, $end);
        $tasks = $this->tasks($project, $start, $end, $worklogs->pluck('task_id')->unique()->all());
        $taskIds = $tasks->pluck('id')->all();

        return new WeeklyReportContext(
            project: $project,
            sprint: $sprint,
            weekNumber: $weekNumber,
            weekStart: $weekStart,
            weekEnd: $weekEnd,
            tasks: $tasks,
            worklogs: $worklogs,
            activities: $this->activities($project, $start, $end),
            blockers: $this->blockers($project, $taskIds),
            feedbacks: $this->feedbacks($project, $start, $end),
        );
    }

    /**
     * Task giao với kỳ: đang làm / review / bị chặn, hoàn thành hoặc cập nhật trong kỳ,
     * có hạn / bắt đầu trong kỳ, quá hạn, hoặc có giờ công trong kỳ — mọi Sprint và backlog.
     *
     * @param  array<int, int>  $worklogTaskIds
     * @return Collection<int, Task>
     */
    private function tasks(Project $project, Carbon $start, Carbon $end, array $worklogTaskIds): Collection
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return Task::query()
            ->with([
                'assignee:id,full_name',
                'assignees:id,full_name',
                'epic:id,name',
                'sprint:id,name',
            ])
            ->where('project_id', $project->id)
            ->where(function ($q) use ($start, $end, $startDate, $endDate, $worklogTaskIds) {
                $q->whereIn('status', [
                    TaskStatus::InProgress->value,
                    TaskStatus::InReview->value,
                    TaskStatus::Blocked->value,
                ])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end])
                    ->orWhereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('due_date', [$startDate, $endDate])
                    ->orWhere(function ($overdue) use ($endDate) {
                        $overdue->whereNotNull('due_date')
                            ->whereDate('due_date', '<', $endDate)
                            ->where('status', '!=', TaskStatus::Done->value);
                    });

                if ($worklogTaskIds !== []) {
                    $q->orWhereIn('id', $worklogTaskIds);
                }
            })
            ->orderBy('order_column')
            ->get();
    }

    /** @return Collection<int, Worklog> */
    private function worklogs(Project $project, Carbon $start, Carbon $end): Collection
    {
        return Worklog::query()
            ->with(['employee:id,full_name', 'task:id,title,project_id'])
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
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
     * Test case còn mở (bất kể thời điểm) + test case gắn task trong kỳ.
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
