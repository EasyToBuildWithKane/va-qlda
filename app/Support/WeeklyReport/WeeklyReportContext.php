<?php

namespace App\Support\WeeklyReport;

use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bộ dữ liệu nguồn cho một tuần báo cáo — do {@see WeeklyReportDataCollector}
 * dựng và truyền cho engine sinh báo cáo.
 */
final class WeeklyReportContext
{
    /**
     * @param  Collection<int, \App\Models\Task>  $tasks  Task giao với khoảng ngày (mọi Sprint + backlog)
     * @param  Collection<int, \App\Models\Worklog>  $worklogs  Worklog trong cửa sổ ngày
     * @param  Collection<int, \App\Models\ProjectActivity>  $activities  Hoạt động trong kỳ
     * @param  Collection<int, \App\Models\Blocker>  $blockers  Test case liên quan
     * @param  Collection<int, \App\Models\Feedback>  $feedbacks  Phản hồi của dự án trong kỳ
     */
    public function __construct(
        public readonly Project $project,
        public readonly ?Sprint $sprint,
        public readonly int $weekNumber,
        public readonly Carbon $weekStart,
        public readonly Carbon $weekEnd,
        public readonly Collection $tasks,
        public readonly Collection $worklogs,
        public readonly Collection $activities,
        public readonly Collection $blockers,
        public readonly Collection $feedbacks,
    ) {}

    public function periodLabel(): string
    {
        return $this->weekStart->format('d/m').' – '.$this->weekEnd->format('d/m/Y');
    }

    public function sprintLabel(): string
    {
        return $this->sprint?->name ?? 'Ngoài Sprint';
    }
}
