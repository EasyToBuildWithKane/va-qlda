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
use App\Support\DashboardPersonnelScope;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
            'systemSnapshot' => $this->buildSystemSnapshot(
                $stats,
                $isLeadTier,
                $isMemberTier,
                $personnelScope,
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
     * Cross-module snapshot for the hub (distinct from /work task KPIs).
     *
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function buildSystemSnapshot(
        array $stats,
        bool $isLeadTier,
        bool $isMemberTier,
        DashboardPersonnelScope $personnelScope,
    ): array {
        $dept = $personnelScope->department();
        $deptLabel = $dept?->name ?? 'Phòng Công nghệ';
        $scopedPeople = $personnelScope->employeeIds()->count();

        $alerts = [];

        if (($stats['overdue_tasks'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'overdue_tasks',
                'label' => 'Công việc quá hạn',
                'value' => $stats['overdue_tasks'],
                'hint' => 'Xem chi tiết trên Dashboard công việc',
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
                'hint' => 'Hàng đợi duyệt báo cáo ngày',
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
                'hint' => 'Cần theo dõi gia hạn',
                'href' => '/contracts/dashboard',
                'tone' => 'amber',
                'icon' => 'contract',
            ];
        }

        if (($stats['open_feedback'] ?? 0) > 0) {
            $alerts[] = [
                'key' => 'open_feedback',
                'label' => 'Phản hồi đang xử lý',
                'value' => $stats['open_feedback'],
                'hint' => 'Luồng phản hồi người dùng',
                'href' => '/feedback',
                'tone' => 'violet',
                'icon' => 'feedback',
            ];
        }

        $domains = [
            [
                'key' => 'engagement',
                'title' => 'Tương tác & chất lượng',
                'icon' => 'feedback',
                'tone' => 'violet',
                'metrics' => [
                    [
                        'label' => 'Phản hồi đang xử lý',
                        'value' => $stats['open_feedback'],
                        'href' => '/feedback',
                    ],
                    [
                        'label' => 'Vướng mắc đang mở',
                        'value' => $stats['open_blockers'],
                        'href' => '/blockers',
                    ],
                ],
            ],
            [
                'key' => 'knowledge',
                'title' => 'Tri thức & đào tạo',
                'icon' => 'knowledge',
                'tone' => 'emerald',
                'metrics' => [
                    [
                        'label' => 'Bài viết đã xuất bản',
                        'value' => $stats['kb_articles'],
                        'href' => '/knowledge-base',
                    ],
                ],
            ],
            [
                'key' => 'portfolio',
                'title' => 'Danh mục dự án',
                'icon' => 'portfolio',
                'tone' => 'brand',
                'metrics' => [
                    [
                        'label' => 'Dự án đang chạy',
                        'value' => $stats['active_projects'],
                        'sub' => "/ {$stats['total_projects']} trong hệ thống",
                        'href' => '/projects',
                    ],
                ],
            ],
            [
                'key' => 'people',
                'title' => 'Con người & tổ chức',
                'icon' => 'org-teams',
                'tone' => 'sky',
                'metrics' => [
                    [
                        'label' => 'Nhân sự trong phạm vi',
                        'value' => $scopedPeople,
                        'sub' => $deptLabel,
                        'href' => '/members',
                    ],
                    [
                        'label' => 'Thành viên toàn công ty',
                        'value' => $stats['total_members'],
                        'sub' => 'Hồ sơ nhân sự',
                        'href' => '/members',
                    ],
                ],
            ],
        ];

        if ($isMemberTier && isset($stats['upcoming_sessions'])) {
            $domains[1]['metrics'][] = [
                'label' => 'Buổi coaching (7 ngày tới)',
                'value' => $stats['upcoming_sessions'],
                'href' => '/coaching',
            ];
        }

        $assetDomain = [
            'key' => 'assets',
            'title' => 'Tài sản & nền tảng',
            'icon' => 'sparkles',
            'tone' => 'violet',
            'metrics' => [
                [
                    'label' => 'Tài khoản AI',
                    'value' => $stats['ai_accounts'],
                    'href' => '/ai-accounts/dashboard',
                ],
            ],
        ];

        if ($isLeadTier) {
            $assetDomain['metrics'][] = [
                'label' => 'Hợp đồng hiệu lực',
                'value' => $stats['active_contracts'] ?? 0,
                'sub' => ($stats['expiring_contracts'] ?? 0) > 0
                    ? "{$stats['expiring_contracts']} sắp hết hạn"
                    : 'CLM',
                'href' => '/contracts/dashboard',
            ];
        }

        if ($isMemberTier && isset($stats['credentials'])) {
            $assetDomain['metrics'][] = [
                'label' => 'Vault mật khẩu',
                'value' => $stats['credentials'],
                'href' => '/credentials',
            ];
        }

        if ($isLeadTier) {
            $domains[3]['metrics'][] = [
                'label' => 'Báo cáo ngày chờ duyệt',
                'value' => $stats['pending_reports'] ?? 0,
                'href' => '/daily-reports/review',
            ];
        }

        // Insert assets before people for visual balance
        array_splice($domains, 3, 0, [$assetDomain]);

        return [
            'alerts' => array_slice($alerts, 0, 4),
            'domains' => $domains,
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
