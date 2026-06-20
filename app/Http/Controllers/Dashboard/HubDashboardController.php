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

        $scopedEmployeeIds = $personnelScope->employeeIds();

        return Inertia::render('Dashboard/Hub', [
            'greeting' => $this->greetingMeta($account),
            'activityTrend' => $this->buildActivityTrend(30),
            'compliance' => $this->buildComplianceSummary($scopedEmployeeIds),
            'alerts' => $this->buildAlerts($stats, $isLeadTier),
            'moduleGroups' => $this->buildModuleGroups(
                $stats,
                $isAdminTier,
                $isLeadTier,
                $isMemberTier,
                $isSuper,
            ),
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
            'total_projects' => Project::count(),
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
        }

        if ($isMemberTier) {
            $stats['credentials'] = Credential::count();
            $stats['upcoming_sessions'] = CoachingSession::where('date', '>=', now()->toDateString())
                ->where('date', '<=', now()->addDays(7)->toDateString())
                ->count();
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
                'statUnit' => 'đang chạy',
                'tone' => 'brand',
            ],
            [
                'key' => 'blockers',
                'label' => 'Vướng mắc',
                'icon' => 'blockers',
                'href' => '/blockers',
                'stat' => $stats['open_blockers'],
                'statUnit' => 'đang mở',
                'tone' => $stats['open_blockers'] > 0 ? 'amber' : 'emerald',
            ],
            [
                'key' => 'tasks',
                'label' => 'Dashboard Công Việc',
                'icon' => 'task',
                'href' => '/work',
                'stat' => $stats['overdue_tasks'],
                'statUnit' => 'quá hạn',
                'tone' => $stats['overdue_tasks'] > 0 ? 'rose' : 'emerald',
            ],
            [
                'key' => 'feedback',
                'label' => 'Phản hồi',
                'icon' => 'feedback',
                'href' => '/feedback',
                'stat' => $stats['open_feedback'],
                'statUnit' => 'đang xử lý',
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
                'statUnit' => 'chờ duyệt',
                'tone' => ($stats['pending_reports'] ?? 0) > 0 ? 'amber' : 'sky',
            ];
        } else {
            $workModules[] = [
                'key' => 'daily-reports',
                'label' => 'Báo cáo ngày',
                'icon' => 'daily',
                'href' => '/daily-reports/today',
                'stat' => null,
                'statUnit' => null,
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
                    'statUnit' => null,
                    'tone' => 'sky',
                ],
                [
                    'key' => 'audit-personnel',
                    'label' => 'Audit nhân sự',
                    'icon' => 'leaderboard',
                    'href' => '/performance/audit',
                    'stat' => null,
                    'statUnit' => null,
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
                'statUnit' => 'bài viết',
                'tone' => 'emerald',
            ],
            [
                'key' => 'congnghe',
                'label' => 'Trung tâm Công Nghệ',
                'icon' => 'rocket',
                'href' => '/congnghe',
                'stat' => null,
                'statUnit' => null,
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
                'statUnit' => '7 ngày',
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
                'statUnit' => 'AI',
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
                'statUnit' => 'HĐ',
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
                'statUnit' => 'vault',
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
                'statUnit' => 'nhân sự',
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
                'statUnit' => null,
                'tone' => 'brand',
            ]);
            $peopleModules[] = [
                'key' => 'departments',
                'label' => 'Phòng ban',
                'icon' => 'department',
                'href' => '/departments',
                'stat' => null,
                'statUnit' => null,
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
                'statUnit' => null,
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
                'statUnit' => null,
                'tone' => 'brand',
            ];
            $systemModules[] = [
                'key' => 'audit-trail',
                'label' => 'Nhật ký truy vết',
                'icon' => 'shield',
                'href' => '/audit',
                'stat' => null,
                'statUnit' => null,
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
                'statUnit' => null,
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
     * Daily completed/created task counts for the last N days (line chart).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildActivityTrend(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1)->toDateString();

        $completed = Task::whereDate('completed_at', '>=', $start)
            ->select(DB::raw('DATE(completed_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->pluck('total', 'd');

        $created = Task::whereDate('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $date = $day->toDateString();
            $series[] = [
                'date' => $date,
                'label' => $day->format('d/m'),
                'completed' => (int) ($completed[$date] ?? 0),
                'created' => (int) ($created[$date] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Team-wide daily-report compliance gauge for the current week (working days only).
     *
     * @param  Collection<int, int>  $employeeIds
     * @return array<string, mixed>
     */
    private function buildComplianceSummary(Collection $employeeIds): array
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

        $expectedPerPerson = $expectedDates->count();
        $people = $employeeIds->count();

        $teamSubmitted = $people > 0 && $expectedPerPerson > 0
            ? DailyReport::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('date', $expectedDates->all())
                ->whereIn('status', $submittedStatuses)
                ->count()
            : 0;

        $teamExpected = $expectedPerPerson * $people;

        return [
            'teamRate' => $teamExpected > 0 ? round($teamSubmitted / $teamExpected * 100, 1) : 0.0,
            'submitted' => $teamSubmitted,
            'expected' => $teamExpected,
            'people' => $people,
            'expectedPerPerson' => $expectedPerPerson,
            'periodLabel' => $weekStart->format('d/m').' – '.$today->format('d/m/Y'),
        ];
    }

    /**
     * Cross-module attention chips (max 4), highest urgency first.
     *
     * @param  array<string, int>  $stats
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(array $stats, bool $isLeadTier): array
    {
        $alerts = [];

        if (($stats['overdue_tasks'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'overdue_tasks',
                'label' => 'Công việc quá hạn',
                'value' => $stats['overdue_tasks'],
                'href' => '/work',
                'tone' => 'rose',
                'icon' => 'clock',
            ];
        }

        if ($isLeadTier && ($stats['pending_reports'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'pending_reports',
                'label' => 'Báo cáo chờ duyệt',
                'value' => $stats['pending_reports'],
                'href' => '/daily-reports/review',
                'tone' => 'amber',
                'icon' => 'daily',
            ];
        }

        if ($isLeadTier && ($stats['expiring_contracts'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'expiring_contracts',
                'label' => 'Hợp đồng sắp hết hạn',
                'value' => $stats['expiring_contracts'],
                'href' => '/contracts/dashboard',
                'tone' => 'amber',
                'icon' => 'contract',
            ];
        }

        if (($stats['open_blockers'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'open_blockers',
                'label' => 'Vướng mắc đang mở',
                'value' => $stats['open_blockers'],
                'href' => '/blockers',
                'tone' => 'amber',
                'icon' => 'blockers',
            ];
        }

        if (($stats['open_feedback'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'open_feedback',
                'label' => 'Phản hồi đang xử lý',
                'value' => $stats['open_feedback'],
                'href' => '/feedback',
                'tone' => 'violet',
                'icon' => 'feedback',
            ];
        }

        return array_slice($alerts, 0, 4);
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

        $name = $account->display_name
            ?: $account->employee?->full_name
            ?: $account->username;

        return [
            'text' => $greeting,
            'date' => now()->locale('vi')->isoFormat('dddd, D MMMM YYYY'),
            'name' => $name,
        ];
    }
}
