<?php

namespace App\Support\Performance;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\Employee;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dựng Timeline Audit cho MỘT nhân viên: theo từng tuần (hoặc tháng với kỳ dài),
 * so "Kế hoạch" (task cam kết: đến hạn/bắt đầu trong tuần) với "Kết quả" (task
 * đã Done), tính tỉ lệ hoàn thành cam kết + điểm hiệu suất khách quan.
 *
 * Kèm text định tính từ Báo cáo ngày (goals_today / plan_tomorrow) của tuần và
 * snapshot Kanban — phục vụ drill-down "xem toàn bộ task thực tế".
 */
class EmployeeAuditBuilder
{
    public function __construct(private readonly PerformanceScorer $scorer) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Employee $member, PerformanceFilter $filter): array
    {
        $tz = config('performance.timezone', 'Asia/Ho_Chi_Minh');

        $tasks = PerformanceTaskScope::forEmployee($member->id, $filter)
            ->with('project:id,name,color')
            ->get();

        $reports = DailyReport::query()
            ->where('employee_id', $member->id)
            ->whereBetween('date', [$filter->start->toDateString(), $filter->end->toDateString()])
            ->orderBy('date')
            ->get(['id', 'date', 'goals_today', 'plan_tomorrow']);

        $buckets = $this->buckets($filter);
        $weeks = collect($buckets)->map(
            fn (array $b) => $this->weekCard($b, $tasks, $reports, $filter)
        )->values();

        $committed = (int) $weeks->sum('summary.committed');
        $done = (int) $weeks->sum('summary.done');
        $avgScore = $weeks->where('summary.committed', '>', 0)->avg('scores.performance');
        $avgScore = $avgScore !== null ? (int) round($avgScore) : 0;

        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->full_name,
                'role' => $member->role_title,
                'avatar' => PublicMediaUrl::fromPublicDisk($member->avatar_path),
            ],
            'summary' => [
                'committed' => $committed,
                'done' => $done,
                'commitmentRate' => $committed > 0 ? (int) round($done / $committed * 100) : 0,
                'avgScore' => $avgScore,
                'grade' => $this->scorer->grade($avgScore),
                'weeks' => $weeks->count(),
            ],
            'trend' => $weeks->map(fn (array $w) => [
                'label' => $w['label'],
                'commitmentRate' => $w['summary']['commitmentRate'],
                'score' => $w['scores']['performance'],
            ])->all(),
            'weeks' => $weeks->all(),
        ];
    }

    /**
     * @param  array{key:string, label:string, start:Carbon, end:Carbon}  $b
     * @param  Collection<int, Task>  $allTasks
     * @param  Collection<int, DailyReport>  $reports
     * @return array<string, mixed>
     */
    private function weekCard(array $b, Collection $allTasks, Collection $reports, PerformanceFilter $filter): array
    {
        $start = $b['start'];
        $end = $b['end'];

        $planned = PerformanceTaskScope::plannedInBucket($allTasks, $start, $end, $filter);

        $plan = $planned->map(function (Task $t) use ($end) {
            $isDone = $t->status === TaskStatus::Done
                && $t->completed_at && $t->completed_at->lte($end);

            return [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status->value,
                'statusLabel' => $t->status->label(),
                'statusColor' => $t->status->color(),
                'priority' => $t->priority->value,
                'priorityLabel' => $t->priority->label(),
                'dueDate' => $t->due_date?->toDateString(),
                'completedAt' => $t->completed_at?->toDateString(),
                'storyPoints' => $t->story_points ? round((float) $t->story_points, 1) : null,
                'project' => $t->project ? ['name' => $t->project->name, 'color' => $t->project->color] : null,
                'result' => $isDone ? 'done' : 'missed',
            ];
        })->sortBy('result')->values()->all();

        $committed = $planned->count();
        $done = $planned->filter(
            fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at && $t->completed_at->lte($end)
        )->count();
        $onTime = $planned->filter(
            fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at
                && $t->due_date && $t->completed_at->lte($t->due_date->copy()->endOfDay())
        )->count();
        $blocked = $planned->where('status', TaskStatus::Blocked)->count();

        $scores = $this->scorer->score([
            'assigned' => $committed,
            'done' => $done,
            'onTime' => $onTime,
            'overdue' => 0,
            'blocked' => $blocked,
        ]);

        $weekReports = $reports->filter(
            fn (DailyReport $r) => $r->date->betweenIncluded($start, $end)
        );

        return [
            'key' => $b['key'],
            'label' => $b['label'],
            'range' => PerformanceDisplay::rangeLabel($start, $end),
            'summary' => [
                'committed' => $committed,
                'done' => $done,
                'missed' => $committed - $done,
                'commitmentRate' => $committed > 0 ? (int) round($done / $committed * 100) : 0,
            ],
            'scores' => $scores,
            'grade' => $this->scorer->grade($scores['performance']),
            'plan' => $plan,
            'kanban' => $this->kanban($planned),
            'reports' => $weekReports->map(fn (DailyReport $r) => [
                'date' => $r->date->toDateString(),
                'goals' => $r->goals_today,
                'plan' => $r->plan_tomorrow,
            ])->values()->all(),
        ];
    }

    /**
     * Snapshot Kanban: nhóm task của tuần theo cột trạng thái.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<array<string, mixed>>
     */
    private function kanban(Collection $tasks): array
    {
        $byStatus = $tasks->groupBy(fn (Task $t) => $t->status->value);

        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'count' => $byStatus->get($s->value, collect())->count(),
                'tasks' => $byStatus->get($s->value, collect())->map(fn (Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'project' => $t->project ? ['name' => $t->project->name, 'color' => $t->project->color] : null,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key:string, label:string, start:Carbon, end:Carbon}>
     */
    private function buckets(PerformanceFilter $filter): array
    {
        // Kỳ dài (năm) → gom theo tháng để tránh quá nhiều thẻ; còn lại theo tuần.
        $byMonth = $filter->periodType === 'year';

        $buckets = [];
        $cursor = $filter->start->copy();
        $guard = 0;

        while ($cursor->lte($filter->end) && $guard++ < 60) {
            if ($byMonth) {
                $start = $cursor->copy()->startOfMonth();
                $end = $cursor->copy()->endOfMonth();
                $label = 'Tháng '.$cursor->format('m/Y');
                $cursor->addMonthNoOverflow()->startOfMonth();
            } else {
                $start = $cursor->copy()->startOfWeek();
                $end = $cursor->copy()->endOfWeek();
                $label = 'Tuần '.$start->format('W');
                $cursor->addWeek()->startOfWeek();
            }

            $buckets[] = [
                'key' => $start->toDateString(),
                'label' => $label,
                'start' => $start->lt($filter->start) ? $filter->start->copy() : $start,
                'end' => $end->gt($filter->end) ? $filter->end->copy() : $end,
            ];
        }

        // Thẻ mới nhất lên đầu timeline.
        return array_reverse($buckets);
    }
}
