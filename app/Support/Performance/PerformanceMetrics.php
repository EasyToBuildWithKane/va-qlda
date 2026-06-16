<?php

namespace App\Support\Performance;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\TaskStatus;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Engine tính toán hiệu suất từ dữ liệu task/worklog thật, theo PerformanceFilter.
 *
 * Tải task của kỳ một lần rồi tổng hợp trong PHP để tránh N truy vấn. Trả về
 * payload đã shape sẵn cho Executive Dashboard (KPI, distribution, trend, bảng
 * per-employee dùng cho leaderboard/workload ở pha sau).
 */
class PerformanceMetrics
{
    public function __construct(private readonly PerformanceScorer $scorer) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PerformanceFilter $filter): array
    {
        $tz = config('performance.timezone', 'Asia/Ho_Chi_Minh');
        $today = Carbon::now($tz)->startOfDay();
        $employeeIds = $filter->employeeIds();

        if ($employeeIds->isEmpty()) {
            return $this->emptyPayload($filter);
        }

        $periodTasks = $this->periodTaskQuery($filter, $filter->start, $filter->end)->get();
        $openTasks = $this->openSnapshotQuery($filter)->get();

        // ── Aggregate toàn cục ──────────────────────────────────────────
        $agg = $this->aggregate($periodTasks, $openTasks, $today);

        $hoursLogged = $this->hoursLogged($employeeIds, $filter->start, $filter->end, $filter->projectId);

        // Kỳ trước → delta cho trend KPI card.
        [$prevStart, $prevEnd] = $filter->previousRange();
        $prevTasks = $this->periodTaskQuery($filter, $prevStart, $prevEnd)->get();
        $prevAgg = $this->aggregate($prevTasks, collect(), $today);

        // ── Per-employee ────────────────────────────────────────────────
        $people = $this->perEmployee($filter, $periodTasks, $openTasks, $hoursLogged['byEmployee'], $today);
        $avgScore = $people->isNotEmpty() ? (int) round($people->avg('score')) : 0;

        $kpis = $this->kpiCards($agg, $prevAgg, $hoursLogged['total'], $avgScore, $filter);

        return [
            'filter' => $filter->toClientArray(),
            'options' => $filter->options(),
            'kpis' => $kpis,
            'summary' => [
                'committed' => $agg['committed'],
                'done' => $agg['done'],
                'in_progress' => $agg['inProgress'],
                'overdue' => $agg['overdue'],
                'completion_rate' => $this->rate($agg['done'], $agg['committed']),
                'on_time_rate' => $this->rate($agg['onTime'], $agg['done']),
                'avg_score' => $avgScore,
                'grade' => $this->scorer->grade($avgScore),
                'people_count' => $people->count(),
            ],
            'headline' => [
                'committed' => $agg['committed'],
                'done' => $agg['done'],
                'completionRate' => $this->rate($agg['done'], $agg['committed']),
                'onTimeRate' => $this->rate($agg['onTime'], $agg['done']),
                'avgScore' => $avgScore,
                'grade' => $this->scorer->grade($avgScore),
            ],
            'statusDistribution' => $this->statusDistribution($periodTasks),
            'workloadDistribution' => $this->workloadDistribution($people),
            'projectContribution' => $this->projectContribution($periodTasks),
            'trend' => $this->trend($filter, $periodTasks),
            'people' => $people->values()->all(),
        ];
    }

    /**
     * Task "trong kỳ" = task của nhân sự phạm vi chạm khoảng thời gian
     * (đến hạn / hoàn thành / bắt đầu làm trong kỳ).
     *
     * @return Builder<Task>
     */
    private function periodTaskQuery(PerformanceFilter $filter, Carbon $start, Carbon $end): Builder
    {
        return PerformanceTaskScope::forEmployees($filter->employeeIds(), $filter, $start, $end)
            ->with(['project:id,name,color', 'assignees:id']);
    }

    /**
     * Snapshot task đang mở (chưa Done) — cho "đang thực hiện" & "quá hạn".
     *
     * @return Builder<Task>
     */
    private function openSnapshotQuery(PerformanceFilter $filter): Builder
    {
        $employeeIds = $filter->employeeIds();

        $query = Task::query()
            ->whereNull('parent_id')
            ->where('status', '!=', TaskStatus::Done)
            ->where(function (Builder $q) use ($employeeIds) {
                $q->whereIn('assignee_id', $employeeIds)
                    ->orWhereHas(
                        'assignees',
                        fn (Builder $a) => $a->whereIn('employees.id', $employeeIds),
                    );
            });

        if ($filter->projectId) {
            $query->where('project_id', $filter->projectId);
        }
        if ($filter->sprintId) {
            $query->where('sprint_id', $filter->sprintId);
        }

        return $query->with('assignees:id');
    }

    /**
     * @param  Collection<int, Task>  $periodTasks
     * @param  Collection<int, Task>  $openTasks
     * @return array{committed:int, done:int, onTime:int, inProgress:int, overdue:int, blocked:int, storyPoints:float}
     */
    private function aggregate(Collection $periodTasks, Collection $openTasks, Carbon $today): array
    {
        $done = $periodTasks->filter(fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at);

        $onTime = $done->filter(
            fn (Task $t) => $t->due_date && $t->completed_at
                && $t->completed_at->lte($t->due_date->copy()->endOfDay())
        );

        $overdue = $openTasks->filter(
            fn (Task $t) => $t->due_date && $t->due_date->lt($today)
        );

        $inProgress = $openTasks->filter(
            fn (Task $t) => in_array($t->status, [TaskStatus::InProgress, TaskStatus::InReview], true)
        );

        return [
            'committed' => $periodTasks->count(),
            'done' => $done->count(),
            'onTime' => $onTime->count(),
            'inProgress' => $inProgress->count(),
            'overdue' => $overdue->count(),
            'blocked' => $periodTasks->where('status', TaskStatus::Blocked)->count(),
            'storyPoints' => (float) $done->sum(fn (Task $t) => (float) $t->story_points),
        ];
    }

    /**
     * @param  Collection<int, Task>  $periodTasks
     * @param  Collection<int, Task>  $openTasks
     * @param  Collection<int, float>  $hoursByEmployee
     * @return Collection<int, array<string, mixed>>
     */
    private function perEmployee(
        PerformanceFilter $filter,
        Collection $periodTasks,
        Collection $openTasks,
        Collection $hoursByEmployee,
        Carbon $today
    ): Collection {
        $employees = Employee::query()
            ->whereIn('id', $filter->employeeIds())
            ->get(['id', 'full_name', 'avatar_path', 'role_title'])
            ->keyBy('id');

        $periodByEmp = PerformanceTaskScope::groupByAssignee($periodTasks, $filter->employeeIds());
        $openByEmp = PerformanceTaskScope::groupByAssignee($openTasks, $filter->employeeIds());

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $employees->map(function (Employee $emp) use ($periodByEmp, $openByEmp, $hoursByEmployee, $today) {
            $agg = $this->aggregate(
                $periodByEmp->get($emp->id, collect()),
                $openByEmp->get($emp->id, collect()),
                $today,
            );

            $scores = $this->scorer->score([
                'assigned' => $agg['committed'],
                'done' => $agg['done'],
                'onTime' => $agg['onTime'],
                'overdue' => $agg['overdue'],
                'blocked' => $agg['blocked'],
            ]);

            $openCount = $openByEmp->get($emp->id, collect())->count();

            return [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'role' => $emp->role_title,
                'avatar' => PublicMediaUrl::fromPublicDisk($emp->avatar_path),
                'committed' => $agg['committed'],
                'done' => $agg['done'],
                'inProgress' => $agg['inProgress'],
                'overdue' => $agg['overdue'],
                'storyPoints' => round($agg['storyPoints'], 1),
                'hoursLogged' => round((float) ($hoursByEmployee[$emp->id] ?? 0), 1),
                'completionRate' => $scores['completion'],
                'onTimeRate' => $scores['onTime'],
                'qualityScore' => $scores['quality'],
                'score' => $scores['performance'],
                'grade' => $this->scorer->grade($scores['performance']),
                'openTasks' => $openCount,
                'load' => $this->loadBand($openCount),
            ];
        })->sortByDesc('score')->values();

        return $rows;
    }

    /**
     * @return array{total:float, byEmployee: Collection<int, float>}
     */
    private function hoursLogged(Collection $employeeIds, Carbon $start, Carbon $end, ?int $projectId): array
    {
        $query = Worklog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($projectId) {
            $query->whereHas('task', fn (Builder $q) => $q->where('project_id', $projectId));
        }

        $byEmployee = $query
            ->selectRaw('employee_id, SUM(hours) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id')
            ->map(fn ($v) => (float) $v);

        return [
            'total' => round((float) $byEmployee->sum(), 1),
            'byEmployee' => $byEmployee,
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @param  array<string, mixed>  $prev
     * @return list<array<string, mixed>>
     */
    private function kpiCards(array $agg, array $prev, float $hours, int $avgScore, PerformanceFilter $filter): array
    {
        $completionRate = $this->rate($agg['done'], $agg['committed']);
        $onTimeRate = $this->rate($agg['onTime'], $agg['done']);
        $activeProjects = $this->activeProjectCount($filter);

        return [
            $this->card('committed', 'Task được giao', $agg['committed'], 'task', 'brand',
                delta: $agg['committed'] - $prev['committed']),
            $this->card('done', 'Task hoàn thành', $agg['done'], 'done', 'emerald',
                delta: $agg['done'] - $prev['done']),
            $this->card('in_progress', 'Đang thực hiện', $agg['inProgress'], 'worklog', 'sky'),
            $this->card('overdue', 'Quá hạn', $agg['overdue'], 'clock', 'rose'),
            $this->card('completion', 'Tỷ lệ hoàn thành', $completionRate, 'performance', 'violet',
                suffix: '%', ring: $completionRate),
            $this->card('on_time', 'Tỷ lệ đúng hạn', $onTimeRate, 'target', 'emerald',
                suffix: '%', ring: $onTimeRate),
            $this->card('avg_score', 'Hiệu suất TB nhân sự', $avgScore, 'talent-score', 'brand',
                suffix: '%', ring: $avgScore),
            $this->card('story_points', 'Story point hoàn thành', round($agg['storyPoints'], 1), 'sprint', 'amber',
                delta: round($agg['storyPoints'] - $prev['storyPoints'], 1)),
            $this->card('hours', 'Giờ làm ghi nhận', $hours, 'clock', 'sky', suffix: ' giờ'),
            $this->card('projects', 'Dự án đang triển khai', $activeProjects, 'projects', 'violet'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(
        string $key,
        string $label,
        int|float $value,
        string $icon,
        string $tone,
        string $suffix = '',
        int|float|null $delta = null,
        ?int $ring = null,
    ): array {
        $card = [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'suffix' => $suffix,
            'icon' => $icon,
            'tone' => $tone,
        ];

        if ($delta !== null) {
            $card['delta'] = $delta;
            $card['trend'] = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        }
        if ($ring !== null) {
            $card['ring'] = max(0, min(100, $ring));
        }

        return $card;
    }

    private function activeProjectCount(PerformanceFilter $filter): int
    {
        $query = Project::query()->where('status', ProjectStatus::Active);

        if ($filter->projectId) {
            $query->whereKey($filter->projectId);
        } elseif ($filter->departmentId) {
            $query->where('department_id', $filter->departmentId);
        }

        return $query->count();
    }

    /**
     * @param  Collection<int, Task>  $periodTasks
     * @return list<array<string, mixed>>
     */
    private function statusDistribution(Collection $periodTasks): array
    {
        $byStatus = $periodTasks->countBy(fn (Task $t) => $t->status->value);

        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'total' => (int) ($byStatus[$s->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $people
     * @return list<array<string, mixed>>
     */
    private function workloadDistribution(Collection $people): array
    {
        return $people
            ->sortByDesc('openTasks')
            ->take(12)
            ->map(fn (array $p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'avatar' => $p['avatar'],
                'openTasks' => $p['openTasks'],
                'load' => $p['load'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Task>  $periodTasks
     * @return list<array<string, mixed>>
     */
    private function projectContribution(Collection $periodTasks): array
    {
        return $periodTasks
            ->filter(fn (Task $t) => $t->project !== null)
            ->groupBy('project_id')
            ->map(function (Collection $tasks) {
                $first = $tasks->first();

                return [
                    'id' => $first->project->id,
                    'name' => $first->project->name,
                    'color' => $first->project->color,
                    'total' => $tasks->count(),
                    'done' => $tasks->where('status', TaskStatus::Done)->count(),
                    'storyPoints' => round((float) $tasks->sum(fn (Task $t) => (float) $t->story_points), 1),
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Trend hiệu suất theo bucket con của kỳ (completion% + đúng hạn% theo thời gian).
     *
     * @param  Collection<int, Task>  $periodTasks
     * @return list<array<string, mixed>>
     */
    private function trend(PerformanceFilter $filter, Collection $periodTasks): array
    {
        $buckets = $this->buckets($filter);

        return collect($buckets)->map(function (array $b) use ($periodTasks) {
            $committed = $periodTasks->filter(
                fn (Task $t) => $t->due_date && $t->due_date->betweenIncluded($b['start'], $b['end'])
            );
            $done = $periodTasks->filter(
                fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at
                    && $t->completed_at->betweenIncluded($b['start'], $b['end'])
            );
            $onTime = $done->filter(
                fn (Task $t) => $t->due_date && $t->completed_at->lte($t->due_date->copy()->endOfDay())
            );

            return [
                'label' => $b['label'],
                'committed' => $committed->count(),
                'done' => $done->count(),
                'completionRate' => $this->rate($done->count(), max($committed->count(), $done->count())),
                'onTimeRate' => $this->rate($onTime->count(), $done->count()),
            ];
        })->all();
    }

    /**
     * @return list<array{key:string, label:string, start:Carbon, end:Carbon}>
     */
    private function buckets(PerformanceFilter $filter): array
    {
        $granularity = match ($filter->periodType) {
            'week' => 'day',
            'quarter', 'year' => 'month',
            default => 'week', // month + sprint → tuần
        };

        $buckets = [];
        $cursor = $filter->start->copy();
        $guard = 0;

        while ($cursor->lte($filter->end) && $guard++ < 60) {
            if ($granularity === 'day') {
                $start = $cursor->copy()->startOfDay();
                $end = $cursor->copy()->endOfDay();
                $label = PerformanceDisplay::dateAtMidnight($cursor);
                $cursor->addDay();
            } elseif ($granularity === 'month') {
                $start = $cursor->copy()->startOfMonth();
                $end = $cursor->copy()->endOfMonth();
                $label = PerformanceDisplay::dateAtMidnight($start);
                $cursor->addMonthNoOverflow()->startOfMonth();
            } else { // week
                $start = $cursor->copy()->startOfWeek();
                $end = $cursor->copy()->endOfWeek();
                $label = 'Tuần '.PerformanceDisplay::dateAtMidnight($start);
                $cursor->addWeek()->startOfWeek();
            }

            $buckets[] = [
                'key' => $start->toDateString(),
                'label' => $label,
                'start' => $start->lt($filter->start) ? $filter->start->copy() : $start,
                'end' => $end->gt($filter->end) ? $filter->end->copy() : $end,
            ];
        }

        return $buckets;
    }

    private function loadBand(int $openTasks): string
    {
        $healthy = (int) config('performance.workload.healthy_max', 8);
        $over = (int) config('performance.workload.overloaded_min', 15);

        return match (true) {
            $openTasks >= $over => 'overloaded',
            $openTasks > $healthy => 'watch',
            default => 'healthy',
        };
    }

    private function rate(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(PerformanceFilter $filter): array
    {
        return [
            'filter' => $filter->toClientArray(),
            'options' => $filter->options(),
            'kpis' => [],
            'summary' => [
                'committed' => 0,
                'done' => 0,
                'in_progress' => 0,
                'overdue' => 0,
                'completion_rate' => 0,
                'on_time_rate' => 0,
                'avg_score' => 0,
                'grade' => 'D',
                'people_count' => 0,
            ],
            'headline' => [
                'committed' => 0, 'done' => 0, 'completionRate' => 0,
                'onTimeRate' => 0, 'avgScore' => 0, 'grade' => 'D',
            ],
            'statusDistribution' => $this->statusDistribution(collect()),
            'workloadDistribution' => [],
            'projectContribution' => [],
            'trend' => [],
            'people' => [],
        ];
    }
}
