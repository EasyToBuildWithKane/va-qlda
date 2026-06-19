<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Controllers\Controller;
use App\Models\AiAccount;
use App\Models\Blocker;
use App\Models\CoachingSession;
use App\Models\Contract;
use App\Models\Credential;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\KbArticle;
use App\Models\Project;
use App\Models\Task;
use App\Support\DailyReportCalendar;
use App\Support\DashboardPersonnelScope;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HubDashboardController extends Controller
{
    public function __invoke(DashboardPersonnelScope $personnelScope): Response
    {
        /** @var \App\Models\SystemAccount $account */
        $account = Auth::guard('system')->user();

        $isAdminTier = $account->isAdminTier();
        $isLeadTier = $account->isLeadTier();
        $isMemberTier = $account->isMemberTier();
        $isSuper = $account->isSuperAdmin();

        $stats = $this->buildStats($isLeadTier, $isMemberTier);

        return Inertia::render('Dashboard/Hub', [
            'moduleGroups' => $this->buildModuleGroups(
                $stats,
                $isAdminTier,
                $isLeadTier,
                $isMemberTier,
                $isSuper,
            ),
            'greeting' => $this->greetingMeta($account),
            'summary' => $this->buildSummary($personnelScope),
            'charts' => $this->buildCharts($isLeadTier, $personnelScope),
        ]);
    }

    /**
     * Compute lightweight aggregate counts used across all module cards.
     *
     * @param  bool  $isLeadTier  admin|super_admin|lead
     * @param  bool  $isMemberTier  all except viewer
     * @return array<string, int>
     */
    private function buildStats(bool $isLeadTier, bool $isMemberTier): array
    {
        $today = Carbon::today()->toDateString();

        $stats = [
            'active_projects' => Project::where('status', ProjectStatus::Active)->count(),
            'open_blockers' => Blocker::open()->count(),
            'open_tasks' => Task::where('status', '!=', TaskStatus::Done)->count(),
            'overdue_tasks' => Task::where('status', '!=', TaskStatus::Done)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count(),
            'total_members' => Employee::count(),
            'kb_articles' => KbArticle::published()->count(),
            'ai_accounts' => AiAccount::count(),
            'open_feedback' => Feedback::open()->count(),
        ];

        if ($isLeadTier) {
            $stats['pending_reports'] = DailyReport::where('status', ReportStatus::Submitted)->count();
            $stats['active_contracts'] = Contract::whereIn('status', [
                ContractStatus::Active->value,
                ContractStatus::ExpiringSoon->value,
                ContractStatus::PendingRenewal->value,
            ])->count();
            $stats['expiring_contracts'] = Contract::where('status', ContractStatus::ExpiringSoon->value)->count();
            $stats['upcoming_sessions'] = CoachingSession::where('date', '>=', now()->toDateString())
                ->where('date', '<=', now()->addDays(7)->toDateString())
                ->count();
        }

        if ($isMemberTier) {
            $stats['credentials'] = Credential::count();
        }

        return $stats;
    }

    /**
     * Build role-filtered module groups for the hub grid.
     *
     * @param  array<string, int>  $stats
     * @return array<int, array<string, mixed>>
     */
    private function buildModuleGroups(
        array $stats,
        bool $isAdminTier,
        bool $isLeadTier,
        bool $isMemberTier,
        bool $isSuper,
    ): array {
        $groups = [];

        // ──────────────────────────────────────────────────────────────
        // 1. Công việc & Dự án
        // ──────────────────────────────────────────────────────────────
        $workModules = [
            [
                'key' => 'projects',
                'label' => 'Dự án',
                'icon' => 'all-projects',
                'href' => '/projects',
                'stat' => $stats['active_projects'],
                'statLabel' => 'đang chạy',
                'tone' => 'brand',
            ],
            [
                'key' => 'blockers',
                'label' => 'Vướng mắc',
                'icon' => 'blockers',
                'href' => '/blockers',
                'stat' => $stats['open_blockers'],
                'statLabel' => 'đang mở',
                'tone' => $stats['open_blockers'] > 0 ? 'amber' : 'emerald',
            ],
            [
                'key' => 'tasks',
                'label' => 'Dashboard Công Việc',
                'icon' => 'task',
                'href' => '/work',
                'stat' => $stats['overdue_tasks'],
                'statLabel' => 'quá hạn',
                'tone' => $stats['overdue_tasks'] > 0 ? 'rose' : 'emerald',
            ],
            [
                'key' => 'feedback',
                'label' => 'Phản hồi',
                'icon' => 'feedback',
                'href' => '/feedback',
                'stat' => $stats['open_feedback'],
                'statLabel' => 'đang xử lý',
                'tone' => 'violet',
            ],
        ];

        if ($isLeadTier) {
            $workModules[] = [
                'key' => 'daily-reports',
                'label' => 'Báo cáo ngày',
                'icon' => 'daily',
                'href' => '/daily-reports/review',
                'stat' => $stats['pending_reports'] ?? 0,
                'statLabel' => 'chờ duyệt',
                'tone' => ($stats['pending_reports'] ?? 0) > 0 ? 'amber' : 'sky',
            ];
        } else {
            $workModules[] = [
                'key' => 'daily-reports',
                'label' => 'Báo cáo ngày',
                'icon' => 'daily',
                'href' => '/daily-reports/today',
                'stat' => null,
                'statLabel' => 'hôm nay',
                'tone' => 'sky',
            ];
        }

        $groups[] = [
            'key' => 'work',
            'label' => 'Công việc & Dự án',
            'icon' => 'projects',
            'tone' => 'brand',
            'modules' => $workModules,
        ];

        // ──────────────────────────────────────────────────────────────
        // 2. Hiệu suất (admin/lead/viewer only)
        // ──────────────────────────────────────────────────────────────
        if (! $isMemberTier || $isAdminTier) {
            $perfModules = [
                [
                    'key' => 'performance',
                    'label' => 'Bảng hiệu suất',
                    'icon' => 'performance',
                    'href' => '/performance',
                    'stat' => null,
                    'statLabel' => 'KPI nhân sự',
                    'tone' => 'sky',
                ],
                [
                    'key' => 'audit-personnel',
                    'label' => 'Audit nhân sự',
                    'icon' => 'leaderboard',
                    'href' => '/performance/audit',
                    'stat' => null,
                    'statLabel' => 'đánh giá nhân viên',
                    'tone' => 'violet',
                ],
            ];

            $groups[] = [
                'key' => 'performance',
                'label' => 'Hiệu suất & Audit',
                'icon' => 'performance',
                'tone' => 'sky',
                'modules' => $perfModules,
            ];
        }

        // ──────────────────────────────────────────────────────────────
        // 3. Đào tạo & Tri thức
        // ──────────────────────────────────────────────────────────────
        $learnModules = [
            [
                'key' => 'knowledge-base',
                'label' => 'Cơ sở tri thức',
                'icon' => 'knowledge',
                'href' => '/knowledge-base',
                'stat' => $stats['kb_articles'],
                'statLabel' => 'bài viết',
                'tone' => 'emerald',
            ],
            [
                'key' => 'congnghe',
                'label' => 'Trung tâm Công Nghệ',
                'icon' => 'rocket',
                'href' => '/congnghe',
                'stat' => null,
                'statLabel' => 'đề xuất phần mềm',
                'tone' => 'violet',
            ],
        ];

        if ($isMemberTier) {
            array_unshift($learnModules, [
                'key' => 'coaching',
                'label' => 'Coaching & Đào tạo',
                'icon' => 'learning',
                'href' => '/coaching',
                'stat' => $stats['upcoming_sessions'] ?? null,
                'statLabel' => 'buổi học sắp tới',
                'tone' => 'sky',
            ]);
        }

        $groups[] = [
            'key' => 'learning',
            'label' => 'Đào tạo & Tri thức',
            'icon' => 'learning',
            'tone' => 'emerald',
            'modules' => $learnModules,
        ];

        // ──────────────────────────────────────────────────────────────
        // 4. Quản lý Tài sản (admin/lead/viewer for contract; member+ for credential)
        // ──────────────────────────────────────────────────────────────
        $assetModules = [
            [
                'key' => 'ai-accounts',
                'label' => 'AI Workspace',
                'icon' => 'sparkles',
                'href' => '/ai-accounts/dashboard',
                'stat' => $stats['ai_accounts'],
                'statLabel' => 'tài khoản AI',
                'tone' => 'violet',
            ],
        ];

        if ($isLeadTier) {
            array_unshift($assetModules, [
                'key' => 'contracts',
                'label' => 'Hợp đồng & NCC',
                'icon' => 'budget',
                'href' => '/contracts/dashboard',
                'stat' => $stats['active_contracts'] ?? 0,
                'statLabel' => 'hợp đồng hiệu lực',
                'tone' => ($stats['expiring_contracts'] ?? 0) > 0 ? 'amber' : 'brand',
            ]);
        }

        if ($isMemberTier) {
            $assetModules[] = [
                'key' => 'credentials',
                'label' => 'Tài khoản & Mật khẩu',
                'icon' => 'vault',
                'href' => '/credentials',
                'stat' => $stats['credentials'] ?? 0,
                'statLabel' => 'tài khoản lưu trữ',
                'tone' => 'slate',
            ];
        }

        $groups[] = [
            'key' => 'assets',
            'label' => 'Quản lý Tài sản',
            'icon' => 'budget',
            'tone' => 'amber',
            'modules' => $assetModules,
        ];

        // ──────────────────────────────────────────────────────────────
        // 5. Tổ chức & Nhân sự
        // ──────────────────────────────────────────────────────────────
        $peopleModules = [
            [
                'key' => 'members',
                'label' => 'Hồ sơ thành viên',
                'icon' => 'members',
                'href' => '/members',
                'stat' => $stats['total_members'],
                'statLabel' => 'nhân sự',
                'tone' => 'sky',
            ],
        ];

        if ($isLeadTier) {
            array_unshift($peopleModules, [
                'key' => 'org-teams',
                'label' => 'Sơ đồ tổ chức',
                'icon' => 'org-teams',
                'href' => '/org-teams',
                'stat' => null,
                'statLabel' => 'sơ đồ nhóm',
                'tone' => 'brand',
            ]);
            $peopleModules[] = [
                'key' => 'departments',
                'label' => 'Phòng ban',
                'icon' => 'department',
                'href' => '/departments',
                'stat' => null,
                'statLabel' => 'quản lý phòng ban',
                'tone' => 'violet',
            ];
        }

        $groups[] = [
            'key' => 'people',
            'label' => 'Tổ chức & Nhân sự',
            'icon' => 'org-teams',
            'tone' => 'sky',
            'modules' => $peopleModules,
        ];

        // ──────────────────────────────────────────────────────────────
        // 6. Hệ thống (admin/super only for most items)
        // ──────────────────────────────────────────────────────────────
        $systemModules = [];

        if ($isMemberTier) {
            $systemModules[] = [
                'key' => 'notifications',
                'label' => 'Thông báo',
                'icon' => 'notifications',
                'href' => '/notifications',
                'stat' => null,
                'statLabel' => 'hộp thư đến',
                'tone' => 'sky',
            ];
        }

        if ($isAdminTier) {
            $systemModules[] = [
                'key' => 'notification-ops',
                'label' => 'Trung tâm vận hành',
                'icon' => 'send',
                'href' => '/notifications/manage',
                'stat' => null,
                'statLabel' => 'quản trị thông báo',
                'tone' => 'brand',
            ];
            $systemModules[] = [
                'key' => 'audit-trail',
                'label' => 'Nhật ký truy vết',
                'icon' => 'shield',
                'href' => '/audit',
                'stat' => null,
                'statLabel' => 'bảo mật hệ thống',
                'tone' => 'slate',
            ];
        }

        if ($isSuper) {
            $systemModules[] = [
                'key' => 'settings',
                'label' => 'Cấu hình hệ thống',
                'icon' => 'system-config',
                'href' => '/settings',
                'stat' => null,
                'statLabel' => 'cấu hình & phân quyền',
                'tone' => 'rose',
            ];
        }

        if ($systemModules !== []) {
            $groups[] = [
                'key' => 'system',
                'label' => 'Hệ thống',
                'icon' => 'settings',
                'tone' => 'slate',
                'modules' => $systemModules,
            ];
        }

        return $groups;
    }

    /**
     * Build the KPI summary strip cards (org pulse) shown above the charts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSummary(DashboardPersonnelScope $personnelScope): array
    {
        $today = Carbon::today()->toDateString();

        // Active projects — load once to derive both the count and avg progress.
        $activeProjects = Project::query()
            ->where('status', ProjectStatus::Active)
            ->with(['tasks:id,project_id,status,parent_id'])
            ->get();

        $totalProjects = Project::count();
        $avgProgress = $activeProjects->isNotEmpty()
            ? (int) round($activeProjects->avg(fn (Project $p) => $p->progress()))
            : 0;

        $totalTasks = Task::count();
        $doneTasks = Task::where('status', TaskStatus::Done)->count();
        $completionRate = $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0;

        $doneLast7 = Task::whereDate('completed_at', '>=', Carbon::today()->subDays(6))->count();
        $donePrev7 = Task::whereBetween('completed_at', [
            Carbon::today()->subDays(13)->startOfDay(),
            Carbon::today()->subDays(7)->endOfDay(),
        ])->count();
        $doneDelta = $doneLast7 - $donePrev7;

        $openBlockers = Blocker::open()->count();
        $overdueTasks = Task::where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $members = $personnelScope->employeeIds()->count();
        $dept = $personnelScope->department();

        return [
            [
                'key' => 'projects',
                'label' => 'Dự án đang chạy',
                'value' => $activeProjects->count(),
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
                'key' => 'completion',
                'label' => 'Tỷ lệ hoàn thành',
                'value' => $completionRate,
                'suffix' => '%',
                'sub' => "{$doneTasks}/{$totalTasks} công việc",
                'icon' => 'check-circle',
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
                'tone' => $openBlockers > 0 ? 'amber' : 'emerald',
            ],
            [
                'key' => 'overdue',
                'label' => 'Công việc quá hạn',
                'value' => $overdueTasks,
                'sub' => 'Chưa xong, đã trễ hạn',
                'icon' => 'clock',
                'tone' => $overdueTasks > 0 ? 'rose' : 'emerald',
            ],
            [
                'key' => 'members',
                'label' => 'Nhân sự hoạt động',
                'value' => $members,
                'sub' => $dept ? $dept->name : 'Phòng Công nghệ',
                'icon' => 'members',
                'tone' => 'violet',
            ],
        ];
    }

    /**
     * Aggregate chart datasets for the dashboard visualisations.
     *
     * @return array<string, mixed>
     */
    private function buildCharts(bool $isLeadTier, DashboardPersonnelScope $personnelScope): array
    {
        $charts = [
            'activityTrend' => $this->activityTrend(30)->values()->all(),
            'projectStatus' => $this->projectStatusDistribution(),
            'taskStatus' => $this->taskStatusDistribution(),
        ];

        if ($isLeadTier) {
            $charts['blockerSeverity'] = $this->blockerSeverityDistribution();
            $charts['reportCompliance'] = $this->reportCompliance($personnelScope);
        }

        return $charts;
    }

    /**
     * Daily created vs completed task counts for the last N days (interactive line).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function activityTrend(int $days): Collection
    {
        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $completed = Task::whereDate('completed_at', '>=', $from)
            ->select(DB::raw('DATE(completed_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->pluck('total', 'd');

        $created = Task::whereDate('created_at', '>=', $from)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $date = $day->toDateString();
            $series->push([
                'date' => $date,
                'label' => $day->format('d/m'),
                'completed' => (int) ($completed[$date] ?? 0),
                'created' => (int) ($created[$date] ?? 0),
            ]);
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectStatusDistribution(): array
    {
        $counts = Project::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($r) => $r->status->value);

        return collect(ProjectStatus::cases())
            ->map(fn (ProjectStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'total' => (int) ($counts->get($s->value)?->getAttribute('total') ?? 0),
            ])
            ->filter(fn ($r) => $r['total'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskStatusDistribution(): array
    {
        $counts = Task::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($r) => $r->status->value);

        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'total' => (int) ($counts->get($s->value)?->getAttribute('total') ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Open blockers grouped by severity (lead tier+).
     *
     * @return array<int, array<string, mixed>>
     */
    private function blockerSeverityDistribution(): array
    {
        $counts = Blocker::open()
            ->select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->get()
            ->keyBy(fn ($r) => $r->severity->value);

        return collect(BlockerSeverity::cases())
            ->map(fn (BlockerSeverity $s) => [
                'severity' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'total' => (int) ($counts->get($s->value)?->getAttribute('total') ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Team-level daily-report submission compliance for the current week (lead tier+).
     *
     * @return array<string, mixed>
     */
    private function reportCompliance(DashboardPersonnelScope $personnelScope): array
    {
        $tz = DailyReportCalendar::timezone();
        $today = Carbon::now($tz)->startOfDay();
        $weekStart = $today->copy()->startOfWeek();
        $workingDays = config('daily_report.working_days', [1, 2, 3, 4, 5, 6]);
        $submittedStatuses = [ReportStatus::Submitted->value, ReportStatus::Reviewed->value];

        $expectedDates = collect();
        for ($day = $weekStart->copy(); $day->lte($today); $day->addDay()) {
            if (in_array($day->isoWeekday(), $workingDays, true)) {
                $expectedDates->push($day->toDateString());
            }
        }

        $employeeIds = $personnelScope->employeeIds();
        $people = $employeeIds->count();
        $expectedPerPerson = $expectedDates->count();
        $teamExpected = $expectedPerPerson * $people;

        $teamSubmitted = $teamExpected > 0
            ? DailyReport::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('date', $expectedDates->all())
                ->whereIn('status', $submittedStatuses)
                ->count()
            : 0;

        return [
            'teamRate' => $teamExpected > 0 ? (int) round($teamSubmitted / $teamExpected * 100) : 0,
            'submitted' => $teamSubmitted,
            'expected' => $teamExpected,
            'people' => $people,
            'expectedPerPerson' => $expectedPerPerson,
            'periodLabel' => $weekStart->format('d/m').' – '.$today->format('d/m'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function greetingMeta(\App\Models\SystemAccount $account): array
    {
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 12 => 'Chào buổi sáng',
            $hour < 18 => 'Chào buổi chiều',
            default => 'Chào buổi tối',
        };

        return [
            'text' => $greeting,
            'date' => now()->locale('vi')->isoFormat('dddd, D MMMM YYYY'),
        ];
    }
}
