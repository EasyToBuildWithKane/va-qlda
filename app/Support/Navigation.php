<?php

namespace App\Support;

use App\Models\SystemAccount;
use App\Support\Auth\CoachingOnlyAccess;

/**
 * Builds the sidebar menu as collapsible groups.
 *
 * Structure  →  Group (collapsible) → Item (link)
 *
 * Nine workflow-aligned groups:
 *
 *   1. overview   — Tổng quan          dashboards
 *   2. daily      — Báo cáo ngày       daily report workflow
 *   3. projects   — Quản lý dự án      project & sprint management
 *   4. quality    — Chất lượng         bugs, feedback, action items
 *   5. people     — Nhân sự            departments, profiles, evaluations
 *   6. knowledge  — Tri thức           knowledge base
 *   7. coaching   — Coaching           dashboard & khóa học / buổi học
 *   8. ai         — AI & Chi phí       AI accounts & purchase proposals
 *   9. admin      — Quản trị           ops center, system config (admin only)
 *
 * Item status flags (used by the sidebar for styling):
 *
 *   live        — đang hoạt động
 *   dev         — đang phát triển
 *   maintenance — đang bảo trì
 *   planned     — sắp ra mắt (href = '#', disabled in UI)
 *
 * Planned items are placed in their logical group (not a separate "upcoming"
 * dump) so that users can see where each feature belongs in the workflow.
 */
class Navigation
{
    /**
     * @return array<int, array{key:string, heading:string, icon:string, variant?:string, defaultCollapsed?:bool, items:array<int, array<string, mixed>>}>
     */
    public static function for(SystemAccount $account): array
    {
        $role = $account->role->value;

        $groups = [];

        foreach (self::definition() as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => ! isset($item['roles']) || \in_array($role, $item['roles'], true),
            ));

            if ($items === []) {
                continue;
            }

            $items = array_map(function (array $item): array {
                $item['status'] ??= $item['href'] === '#' ? 'planned' : 'live';

                return $item;
            }, $items);

            $groups[] = [
                'key' => $group['key'],
                'heading' => $group['heading'],
                'icon' => $group['icon'],
                'variant' => $group['variant'] ?? null,
                'defaultCollapsed' => $group['defaultCollapsed'] ?? false,
                'items' => $items,
            ];
        }

        if (CoachingOnlyAccess::appliesTo($account)) {
            $groups = array_values(array_filter(
                $groups,
                static fn (array $group): bool => $group['key'] === 'coaching',
            ));
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definition(): array
    {
        return [

            // ──────────────────────────────────────────────────────────────
            // 1. TỔNG QUAN — bảng điều khiển
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'overview',
                'heading' => 'Tổng quan',
                'icon' => 'dashboard',
                'items' => [
                    [
                        'label' => 'Bảng điều khiển',
                        'icon' => 'overview',
                        'href' => '/dashboard',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 2. BÁO CÁO NGÀY — daily report write → review → approve loop
            //    Weekly evaluation follows the same approval chain.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'daily',
                'heading' => 'Báo cáo ngày',
                'icon' => 'daily',
                'items' => [
                    [
                        'label' => 'Báo cáo hôm nay',
                        'icon' => 'report-today',
                        'href' => '/daily-reports/today',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                    [
                        'label' => 'Lịch sử báo cáo',
                        'icon' => 'report-history',
                        'href' => '/daily-reports',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Chờ phê duyệt',
                        'icon' => 'review-reports',
                        'href' => '/daily-reports/review',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Đánh giá tuần',
                        'icon' => 'weekly',
                        'href' => '#',
                        'status' => 'planned',
                        'roles' => ['admin', 'lead'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 3. QUẢN LÝ DỰ ÁN — projects, blockers, portfolio
            //    Sprints / tasks / board are accessed inside a project page,
            //    so they are not top-level nav items here.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'projects',
                'heading' => 'Quản lý dự án',
                'icon' => 'projects',
                'items' => [
                    [
                        'label' => 'Tất cả dự án',
                        'icon' => 'all-projects',
                        'href' => '/projects',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Vướng mắc',
                        'icon' => 'blockers',
                        'href' => '/blockers',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Danh mục tổng hợp',
                        'icon' => 'portfolio',
                        'href' => '#',
                        'status' => 'planned',
                        'roles' => ['admin', 'lead'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 4. CHẤT LƯỢNG — bugs, feedback, action items
            //    These all track defects and improvement signals from work.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'quality',
                'heading' => 'Chất lượng',
                'icon' => 'bug',
                'items' => [
                    [
                        'label' => 'Quản lý lỗi',
                        'icon' => 'bug',
                        'href' => '/bugs',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Theo dõi phản hồi',
                        'icon' => 'feedback',
                        'href' => '/feedback',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Hạng mục hành động',
                        'icon' => 'action-items',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 5. NHÂN SỰ & HIỆU SUẤT — org structure, members, evaluations
            //    Departments (live) + member directory, team eval, leaderboard.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'people',
                'heading' => 'Nhân sự & Hiệu suất',
                'icon' => 'members',
                'items' => [
                    [
                        'label' => 'Phòng ban',
                        'icon' => 'department',
                        'href' => '/departments',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Quản lý team',
                        'icon' => 'org-teams',
                        'href' => '/org-teams',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Hồ sơ thành viên',
                        'icon' => 'member-profiles',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                    [
                        'label' => 'Đánh giá nhóm',
                        'icon' => 'team-eval',
                        'href' => '#',
                        'status' => 'planned',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Bảng xếp hạng',
                        'icon' => 'leaderboard',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 6. TRI THỨC & CƠ SỞ — knowledge base, meetings, dev standards
            //    Collapsed by default to keep the sidebar clean.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'knowledge',
                'heading' => 'Tri thức & Cơ sở',
                'icon' => 'knowledge',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'Cơ sở tri thức',
                        'icon' => 'knowledge',
                        'href' => '/knowledge-base',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Cuộc họp & Biên bản',
                        'icon' => 'meeting',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                    [
                        'label' => 'Chuẩn lập trình',
                        'icon' => 'coding-standards',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                    [
                        'label' => 'Git & Phiên bản',
                        'icon' => 'git',
                        'href' => '#',
                        'status' => 'planned',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 7. COACHING — dashboard tài chính & quản lý khóa / buổi học
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'coaching',
                'heading' => 'Coaching & Mentoring',
                'icon' => 'weekly',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'icon' => 'overview',
                        'href' => '/coaching',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                    [
                        'label' => 'Khóa học',
                        'icon' => 'knowledge',
                        'href' => '/coaching/courses',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 8. AI & CHI PHÍ — AI account pool + purchase proposals
            //    All active accounts can request or view AI tools.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'ai',
                'heading' => 'AI & Chi phí',
                'icon' => 'cost',
                'items' => [
                    [
                        'label' => 'Dashboard AI',
                        'icon' => 'overview',
                        'href' => '/ai-accounts/dashboard',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Báo cáo phân tích',
                        'icon' => 'performance',
                        'href' => '/ai-accounts/analytics',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Tài khoản AI',
                        'icon' => 'account',
                        'href' => '/ai-accounts',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'PĐX & ĐNTT',
                        'icon' => 'budget',
                        'href' => '/ai-accounts/cost-report',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Chi phí theo nhóm',
                        'icon' => 'money',
                        'href' => '/ai-accounts/cost-by-group',
                        'status' => 'live',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 9. QUẢN TRỊ — operational monitoring + system config
            //    Admin-only. Invisible to lead / member / viewer.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'admin',
                'heading' => 'Quản trị',
                'icon' => 'settings',
                'items' => [
                    [
                        'label' => 'Trung tâm vận hành',
                        'icon' => 'notifications',
                        'href' => '/notifications/manage',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Cấu hình hệ thống',
                        'icon' => 'system-config',
                        'href' => '/settings',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                ],
            ],
        ];
    }
}
