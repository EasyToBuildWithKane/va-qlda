<?php

namespace App\Support;

use App\Models\SystemAccount;
use App\Support\Auth\CoachingOnlyAccess;
use App\Support\Enums\SystemRole;

/**
 * Builds the sidebar menu as collapsible groups.
 *
 * Structure  →  Group (collapsible) → Item (link)
 *
 * Groups follow the user's mental model (business domains, not technical
 * modules) so related features sit together — "tôi muốn …" navigation:
 *
 *    1. overview   — Tổng quan             bảng điều khiển
 *    2. congnghe   — Trung tâm Công Nghệ   landing, đề xuất phần mềm, quản trị
 *    3. projects   — Công việc & Dự án     dự án, vướng mắc
 *    4. daily      — Báo cáo               báo cáo ngày: viết → duyệt
 *    5. people     — Tổ chức & Nhân sự     sơ đồ, phòng ban, thành viên, hồ sơ
 *    6. coaching   — Đào tạo & Coaching    dashboard, khóa học, buổi học
 *    7. knowledge  — Tri thức & Nội dung   cơ sở tri thức
 *    8. ai         — AI Workspace          tài khoản AI, phân tích, chi phí
 *    9. quality    — Chất lượng & Phản hồi feedback
 *   10. system     — Hệ thống              thông báo, vận hành, cấu hình
 *
 * Only live, route-backed destinations appear here (real routes only) so the
 * sidebar stays low-noise; future features are added to their group when built.
 *
 * Group key `coaching` is load-bearing — CoachingOnlyAccess (in for()) and the
 * coaching-only nav test key off it, so it must not be renamed.
 *
 * Item status flags (used by the sidebar for styling):
 *
 *   live        — đang hoạt động
 *   dev         — đang phát triển
 *   maintenance — đang bảo trì
 *   planned     — sắp ra mắt (href = '#', disabled in UI)
 */
class Navigation
{
    /**
     * @return array<int, array{key:string, heading:string, icon:string, variant?:string, defaultCollapsed?:bool, items:array<int, array<string, mixed>>}>
     */
    public static function for(SystemAccount $account): array
    {
        $role = $account->role->value;
        $isSuper = $account->role === SystemRole::SuperAdmin;

        $groups = [];

        foreach (self::definition() as $group) {
            // Super-admin-only groups (system configuration) are hidden from
            // every other role, including admin.
            if (($group['superOnly'] ?? false) && ! $isSuper) {
                continue;
            }

            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => (
                    ! isset($item['roles'])
                    || \in_array($role, $item['roles'], true)
                    // Super admin is a superset of admin: it sees every admin item.
                    || ($isSuper && \in_array('admin', $item['roles'], true))
                ) && ! \in_array($role, $item['hideForRoles'] ?? [], true),
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
            // 1b. HIỆU SUẤT & AUDIT — analytics + audit nhân sự (quản lý)
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'performance',
                'heading' => 'Hiệu suất & Audit',
                'icon' => 'performance',
                'items' => [
                    [
                        'label' => 'Bảng hiệu suất',
                        'icon' => 'performance',
                        'href' => '/performance',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                    [
                        'label' => 'Audit nhân sự',
                        'icon' => 'leaderboard',
                        'href' => '/performance/audit',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 2. TRUNG TÂM CÔNG NGHỆ — landing + đề xuất phần mềm + quản trị
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'congnghe',
                'heading' => 'Trung tâm Công Nghệ',
                'icon' => 'rocket',
                'items' => [
                    [
                        'label' => 'Landing Công Nghệ',
                        'icon' => 'rocket',
                        'href' => '/congnghe',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Gửi đề xuất phần mềm',
                        'icon' => 'send',
                        'href' => '/congnghe/de-xuat',
                        'status' => 'live',
                        'hideForRoles' => ['admin'],
                    ],
                    [
                        'label' => 'Đề xuất của tôi',
                        'icon' => 'documents',
                        'href' => '/congnghe/de-xuat-cua-toi',
                        'status' => 'live',
                        'hideForRoles' => ['admin'],
                    ],
                    [
                        'label' => 'Quản lý đề xuất',
                        'icon' => 'template',
                        'href' => '/congnghe/proposals',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                        'badgeKey' => 'proposals_new',
                    ],
                    [
                        'label' => 'Quản trị trang',
                        'icon' => 'edit',
                        'href' => '/congnghe/quan-tri',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 3. CÔNG VIỆC & DỰ ÁN — projects, blockers
            //    Sprints / tasks / board are accessed inside a project page,
            //    so they are not top-level nav items here.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'projects',
                'heading' => 'Công việc & Dự án',
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
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 4. BÁO CÁO — daily report write → review → approve loop
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'daily',
                'heading' => 'Báo cáo',
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
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 5. TỔ CHỨC & NHÂN SỰ — org structure, members
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'people',
                'heading' => 'Tổ chức & Nhân sự',
                'icon' => 'org-teams',
                'items' => [
                    [
                        'label' => 'Sơ đồ tổ chức',
                        'icon' => 'org-teams',
                        'href' => '/org-teams',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Phòng ban',
                        'icon' => 'department',
                        'href' => '/departments',
                        'status' => 'live',
                        'roles' => ['admin', 'lead'],
                    ],
                    [
                        'label' => 'Thành viên',
                        'icon' => 'members',
                        'href' => '/org-teams/members',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Hồ sơ thành viên',
                        'icon' => 'member-profiles',
                        'href' => '/members',
                        'status' => 'live',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 6. ĐÀO TẠO & COACHING — dashboard, khóa học, buổi học
            //    Group key `coaching` is load-bearing (CoachingOnlyAccess +
            //    CoachingGoogleGuestTest) — do not rename.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'coaching',
                'heading' => 'Đào tạo & Coaching',
                'icon' => 'learning',
                'items' => [
                    [
                        'label' => 'Coaching Dashboard',
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
                    [
                        'label' => 'Lịch buổi học',
                        'icon' => 'calendar',
                        'href' => '/coaching/sessions/schedule',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                    [
                        'label' => 'Danh sách buổi học',
                        'icon' => 'weekly',
                        'href' => '/coaching/sessions',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 7. TRI THỨC & NỘI DUNG — knowledge base
            //    Collapsed by default to keep the sidebar clean.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'knowledge',
                'heading' => 'Tri thức & Nội dung',
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
                        'label' => 'Blog',
                        'icon' => 'documents',
                        'href' => '/knowledge-base/blog',
                        'status' => 'live',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 8. AI WORKSPACE — AI account pool, analytics, chi phí
            //    All active accounts can request or view AI tools.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'ai',
                'heading' => 'AI Workspace',
                'icon' => 'sparkles',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'AI Dashboard',
                        'icon' => 'overview',
                        'href' => '/ai-accounts/dashboard',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Phân tích sử dụng',
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
                        'label' => 'Chi phí AI',
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
            // 8b. HỢP ĐỒNG (CLM) — vòng đời hợp đồng, NCC, gia hạn, chi phí
            //     Admin/Lead quản lý; Viewer chỉ xem dashboard & báo cáo.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'contracts',
                'heading' => 'Quản lý Hợp đồng',
                'icon' => 'budget',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'Dashboard hợp đồng',
                        'icon' => 'overview',
                        'href' => '/contracts/dashboard',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                    [
                        'label' => 'Danh mục hợp đồng',
                        'icon' => 'documents',
                        'href' => '/contracts',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                    [
                        'label' => 'Nhà cung cấp',
                        'icon' => 'org-teams',
                        'href' => '/contracts/vendors',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                    [
                        'label' => 'Chi phí',
                        'icon' => 'money',
                        'href' => '/contracts/cost',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                    [
                        'label' => 'Báo cáo',
                        'icon' => 'team-eval',
                        'href' => '/contracts/reports',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'viewer'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 9. CHẤT LƯỢNG & PHẢN HỒI — feedback
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'quality',
                'heading' => 'Chất lượng & Phản hồi',
                'icon' => 'feedback',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'Theo dõi phản hồi',
                        'icon' => 'feedback',
                        'href' => '/feedback',
                        'status' => 'live',
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 10. BẢO MẬT & TÀI SẢN SỐ — credential vault
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'security',
                'heading' => 'Bảo mật & Tài sản Số',
                'icon' => 'vault',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'Tài khoản & Mật khẩu',
                        'icon' => 'vault',
                        'href' => '/credentials',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 11. HỆ THỐNG — notifications, ops monitoring, system config
            //     Thông báo mở cho mọi vai trò; vận hành & cấu hình admin-only.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'system',
                'heading' => 'Hệ thống',
                'icon' => 'settings',
                'defaultCollapsed' => true,
                'items' => [
                    [
                        'label' => 'Thông báo',
                        'icon' => 'notifications',
                        'href' => '/notifications',
                        'status' => 'live',
                        'hideForRoles' => ['admin'],
                        'badgeKey' => 'notifications_unread',
                    ],
                    [
                        'label' => 'Trung tâm vận hành',
                        'icon' => 'send',
                        'href' => '/notifications/manage',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 12. CẤU HÌNH HỆ THỐNG — các tab /settings tách thành menu con
            //     Admin-only. Mỗi mục trỏ tới /settings/{group}; nhãn/icon
            //     phản chiếu SettingsSchema::groups().
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'settings',
                'heading' => 'Cấu hình hệ thống',
                'icon' => 'system-config',
                'defaultCollapsed' => true,
                'superOnly' => true,
                'items' => [
                    [
                        'label' => 'Chung',
                        'icon' => 'settings',
                        'href' => '/settings/general',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Đăng nhập & Bảo mật',
                        'icon' => 'account',
                        'href' => '/settings/auth',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Thông báo Telegram',
                        'icon' => 'send',
                        'href' => '/settings/telegram',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Email & Thông báo',
                        'icon' => 'mail',
                        'href' => '/settings/email',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Hợp đồng (CLM)',
                        'icon' => 'budget',
                        'href' => '/settings/clm',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Phân quyền',
                        'icon' => 'members',
                        'href' => '/settings/permissions',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                    [
                        'label' => 'Tài khoản & Vai trò',
                        'icon' => 'account',
                        'href' => '/settings/accounts',
                        'status' => 'live',
                        'roles' => ['admin'],
                    ],
                ],
            ],
        ];
    }
}
