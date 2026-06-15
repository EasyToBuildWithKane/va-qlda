<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Controllers\Controller;
use App\Models\Blocker;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskStatus;
use App\Support\ProjectActivityFeedBuilder;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ProjectActivityFeedBuilder $activityFeed): Response
    {
        $today = Carbon::today()->toDateString();

        // ---- Active projects (loaded once, reused for cards + avg progress) --
        $activeProjectModels = Project::query()
            ->where('status', ProjectStatus::Active)
            ->with([
                'tasks:id,project_id,status,parent_id',
                'members:id,full_name,avatar_path',
            ])
            ->withCount(['blockers as open_blockers_count' => fn ($q) => $q->open()])
            ->get();

        $activeProjectCards = $activeProjectModels
            ->map(fn (Project $p) => $this->projectCard($p))
            ->values();

        $avgProgress = $activeProjectCards->isNotEmpty()
            ? (int) round($activeProjectCards->avg('progress'))
            : 0;

        $activeProjects = $activeProjectCards
            ->sortBy(fn (array $p) => $p['daysLeft'] ?? PHP_INT_MAX)
            ->take(12)
            ->values();

        // ---- KPI cards (formatted for KpiSummaryStrip) ----------------------
        $totalProjects = Project::count();
        $totalMembers = Employee::where('is_active', true)->count();
        $totalTasks = Task::count();
        $doneTasks = Task::where('status', TaskStatus::Done)->count();
        $openBlockers = Blocker::open()->count();
        $overdueTasks = Task::where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $doneLast7 = Task::whereDate('completed_at', '>=', Carbon::today()->subDays(6))->count();
        $donePrev7 = Task::whereBetween('completed_at', [
            Carbon::today()->subDays(13)->startOfDay(),
            Carbon::today()->subDays(7)->endOfDay(),
        ])->count();
        $doneDelta = $doneLast7 - $donePrev7;

        $kpiCards = [
            [
                'key' => 'projects',
                'label' => 'Dự án đang chạy',
                'value' => $activeProjectModels->count(),
                'sub' => "/ {$totalProjects} tổng dự án",
                'icon' => 'projects',
                'tone' => 'brand',
            ],
            [
                'key' => 'progress',
                'label' => 'Tiến độ trung bình',
                'value' => $avgProgress,
                'suffix' => '%',
                'sub' => 'Trên các dự án đang chạy',
                'icon' => 'performance',
                'tone' => 'sky',
            ],
            [
                'key' => 'tasks_done',
                'label' => 'Công việc hoàn thành',
                'value' => $doneTasks,
                'sub' => "/ {$totalTasks} công việc",
                'icon' => 'task',
                'tone' => 'emerald',
                'trend' => [
                    'text' => ($doneDelta >= 0 ? '+' : '').$doneDelta.' so với tuần trước',
                    'tone' => $doneDelta > 0 ? 'good' : ($doneDelta < 0 ? 'bad' : 'neutral'),
                    'arrow' => $doneDelta > 0 ? '↑' : ($doneDelta < 0 ? '↓' : '→'),
                ],
            ],
            [
                'key' => 'blockers',
                'label' => 'Vướng mắc đang mở',
                'value' => $openBlockers,
                'sub' => 'Cần xử lý',
                'icon' => 'blockers',
                'tone' => 'amber',
            ],
            [
                'key' => 'overdue',
                'label' => 'Công việc quá hạn',
                'value' => $overdueTasks,
                'sub' => 'Chưa xong, đã trễ hạn',
                'icon' => 'clock',
                'tone' => 'rose',
            ],
            [
                'key' => 'members',
                'label' => 'Thành viên hoạt động',
                'value' => $totalMembers,
                'sub' => 'Nhân sự đang làm việc',
                'icon' => 'people',
                'tone' => 'violet',
            ],
        ];

        // ---- Daily pulse (today) -------------------------------------------
        $reportedToday = DailyReport::whereDate('date', $today)
            ->distinct('employee_id')
            ->count('employee_id');
        $submittedToday = DailyReport::whereDate('date', $today)
            ->whereIn('status', [ReportStatus::Submitted->value, ReportStatus::Reviewed->value])
            ->count();
        $lateToday = DailyReport::whereDate('date', $today)->where('is_late', true)->count();
        $pendingReview = DailyReport::pendingReview()->count();

        $completedToday = Task::whereDate('completed_at', $today)->count();
        $dueTodayCount = Task::where('status', '!=', TaskStatus::Done)
            ->whereDate('due_date', $today)
            ->count();

        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $hoursToday = (float) Worklog::whereDate('date', $today)->sum('hours');
        $hoursThisWeek = (float) Worklog::whereDate('date', '>=', $startOfWeek)->sum('hours');
        $costThisWeek = (float) Worklog::whereDate('date', '>=', $startOfWeek)->sum('cost');

        $dailyPulse = [
            'reportedToday' => $reportedToday,
            'expectedToday' => $totalMembers,
            'submittedToday' => $submittedToday,
            'lateToday' => $lateToday,
            'pendingReview' => $pendingReview,
            'completedToday' => $completedToday,
            'dueToday' => $dueTodayCount,
            'overdue' => $overdueTasks,
            'hoursToday' => round($hoursToday, 1),
            'hoursThisWeek' => round($hoursThisWeek, 1),
            'costThisWeek' => round($costThisWeek),
            'hoursTrend' => $this->hoursTrend(14),
        ];

        // ---- Due today / overdue task lists --------------------------------
        $dueToday = Task::query()
            ->where('status', '!=', TaskStatus::Done)
            ->whereDate('due_date', $today)
            ->with(['project:id,name,color', 'assignee:id,full_name,avatar_path'])
            ->orderByDesc('priority')
            ->limit(8)
            ->get()
            ->map(fn (Task $t) => $this->taskRow($t))
            ->values();

        $overdueList = Task::query()
            ->where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->with(['project:id,name,color', 'assignee:id,full_name,avatar_path'])
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(fn (Task $t) => $this->taskRow($t))
            ->values();

        // ---- Distributions & trends ----------------------------------------
        $projectsByStatus = Project::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status->value,
                'label' => $r->status->label(),
                'color' => $r->status->color(),
                'total' => (int) $r->getAttribute('total'),
            ]);

        $tasksByStatus = Task::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status->value,
                'label' => $r->status->label(),
                'color' => $r->status->color(),
                'total' => (int) $r->getAttribute('total'),
            ]);

        $blockersBySeverity = Blocker::open()
            ->select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->get()
            ->map(fn ($r) => [
                'severity' => $r->severity->value,
                'label' => $r->severity->label(),
                'color' => $r->severity->color(),
                'total' => (int) $r->getAttribute('total'),
            ]);

        $completionTrend = $this->completionTrend(30);

        return Inertia::render('Dashboard/Index', [
            'kpiCards' => $kpiCards,
            'headline' => [
                'doneTasks' => $doneTasks,
                'totalTasks' => $totalTasks,
                'completionRate' => $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0,
            ],
            'dailyPulse' => $dailyPulse,
            'activeProjects' => $activeProjects,
            'dueToday' => $dueToday,
            'overdueTasks' => $overdueList,
            'activityFeed' => $activityFeed->forSystem(60),
            'projectsByStatus' => $projectsByStatus,
            'tasksByStatus' => $tasksByStatus,
            'blockersBySeverity' => $blockersBySeverity,
            'completionTrend' => $completionTrend,
        ]);
    }

    /**
     * Format one active project into a dashboard card payload.
     *
     * @return array<string, mixed>
     */
    private function projectCard(Project $p): array
    {
        $rootTasks = $p->tasks->whereNull('parent_id');
        $tasksTotal = $rootTasks->count();
        $tasksDone = $rootTasks->where('status', TaskStatus::Done)->count();

        $daysLeft = $p->due_date
            ? (int) Carbon::today()->diffInDays($p->due_date, false)
            : null;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'color' => $p->color,
            'status' => $p->status->value,
            'statusLabel' => $p->status->label(),
            'statusColor' => $p->status->color(),
            'progress' => $p->progress(),
            'tasksDone' => $tasksDone,
            'tasksTotal' => $tasksTotal,
            'dueDate' => $p->due_date?->toDateString(),
            'daysLeft' => $daysLeft,
            'openBlockers' => (int) $p->getAttribute('open_blockers_count'),
            'membersTotal' => $p->members->count(),
            'members' => $p->members->take(5)->map(fn (Employee $m) => [
                'name' => $m->full_name,
                'avatar' => PublicMediaUrl::fromPublicDisk($m->avatar_path),
            ])->values(),
        ];
    }

    /**
     * Format one task into a compact list-row payload.
     *
     * @return array<string, mixed>
     */
    private function taskRow(Task $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status->value,
            'priority' => $t->priority->value,
            'priorityLabel' => $t->priority->label(),
            'priorityColor' => $t->priority->color(),
            'dueDate' => $t->due_date?->toDateString(),
            'project' => $t->project ? [
                'id' => $t->project->id,
                'name' => $t->project->name,
                'color' => $t->project->color,
            ] : null,
            'assignee' => $t->assignee ? [
                'name' => $t->assignee->full_name,
                'avatar' => PublicMediaUrl::fromPublicDisk($t->assignee->avatar_path),
            ] : null,
        ];
    }

    /**
     * Daily completed-task counts for the last N days (line chart).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function completionTrend(int $days): \Illuminate\Support\Collection
    {
        $rows = Task::whereDate('completed_at', '>=', Carbon::today()->subDays($days - 1))
            ->select(DB::raw('DATE(completed_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        return $this->fillDays($days, fn (string $date) => (int) ($rows->get($date)?->total ?? 0));
    }

    /**
     * Daily logged-hours totals for the last N days (sparkline).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function hoursTrend(int $days): \Illuminate\Support\Collection
    {
        $rows = Worklog::whereDate('date', '>=', Carbon::today()->subDays($days - 1))
            ->select(DB::raw('DATE(date) as d'), DB::raw('SUM(hours) as total'))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        return $this->fillDays(
            $days,
            fn (string $date) => round((float) ($rows->get($date)?->total ?? 0), 1),
            'value',
        );
    }

    /**
     * Build a gap-free daily series for the last N days.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function fillDays(int $days, callable $valueFor, string $valueKey = 'total'): \Illuminate\Support\Collection
    {
        $series = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $date = $day->toDateString();
            $series->push([
                'date' => $date,
                'label' => $day->format('d/m'),
                $valueKey => $valueFor($date),
            ]);
        }

        return $series;
    }
}
