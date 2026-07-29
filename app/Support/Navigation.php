<?php

namespace App\Support;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

/**
 * Builds the sidebar menu as a two-tier hierarchy.
 *
 * Structure → Super-section (tier-1 title) → Group (collapsible) → Item (link)
 *
 * Tier-1 super-sections (see self::SECTIONS) bucket the domain groups by
 * business function so the sidebar reads as a few clear chapters instead of a
 * long flat list. Each group declares its `section`; for() sorts groups into
 * SECTIONS order (stable, so definition order is kept within a section) and the
 * frontend renders the section title above the first group of each section:
 *
 *   execution  — Điều hành & Thực thi   overview, performance, projects, daily, ai, contracts, quality
 *   people     — Tri thức & Nội dung    knowledge
 *   technology — Công nghệ              congnghe
 *   system     — Hệ thống & Quản trị    security, system, settings
 *
 * Groups themselves follow the user's mental model (business domains, not
 * technical modules) — "tôi muốn …" navigation.
 *
 * Only live, route-backed destinations appear here (real routes only) so the
 * sidebar stays low-noise; future features are added to their group when built.
 * To add a group: give it a `section` key. To add a section: add it to
 * self::SECTIONS (key order = sidebar order) and point groups at it.
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
     * Tier-1 functional super-sections. The order of keys here is the order the
     * sections appear in the sidebar; every group declares which section it
     * belongs to via its `section` key and groups are bucketed/sorted into these.
     *
     * @var array<string, string>
     */
    private const SECTIONS = [
        'execution' => 'Điều hành & Thực thi',
        'people' => 'Tri thức & Nội dung',
        'technology' => 'Công nghệ',
        'system' => 'Hệ thống & Quản trị',
    ];

    /**
     * Group keys that can never be hidden by the global menu-visibility setting
     * (/settings/menu). `settings` hosts that very config page — hiding it would
     * lock the super admin out of re-enabling anything.
     *
     * @var array<int, string>
     */
    public const PROTECTED_GROUP_KEYS = ['settings'];

    /**
     * @return array<int, array{key:string, section:?string, sectionKey:?string, heading:string, icon:string, variant?:string, defaultCollapsed?:bool, items:array<int, array<string, mixed>>}>
     */
    public static function for(SystemAccount $account): array
    {
        $role = $account->role->value;
        $isSuper = $account->role === SystemRole::SuperAdmin;

        // Groups the super admin has hidden system-wide via /settings/menu.
        // Applies to everyone; protected groups are never affected.
        $hidden = array_values(array_diff(
            array_map('strval', (array) config('va.menu_hidden_groups', [])),
            self::PROTECTED_GROUP_KEYS,
        ));

        $groups = [];

        foreach (self::definition() as $group) {
            // Super-admin-only groups (system configuration) are hidden from
            // every other role, including admin.
            if (($group['superOnly'] ?? false) && ! $isSuper) {
                continue;
            }

            // Globally hidden by the menu-visibility setting (applies to all roles).
            if (\in_array($group['key'], $hidden, true)) {
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
                'section' => self::SECTIONS[$group['section']] ?? null,
                'sectionKey' => $group['section'] ?? null,
                'heading' => $group['heading'],
                'icon' => $group['icon'],
                'variant' => $group['variant'] ?? null,
                'defaultCollapsed' => $group['defaultCollapsed'] ?? false,
                'items' => $items,
            ];
        }

        // Bucket groups into their functional super-section. usort is stable in
        // PHP 8, so the definition order is preserved within each section.
        $order = array_keys(self::SECTIONS);
        $rank = static function (?string $key) use ($order): int {
            $i = array_search($key, $order, true);

            return $i === false ? \PHP_INT_MAX : $i;
        };
        usort($groups, static fn (array $a, array $b): int => $rank($a['sectionKey']) <=> $rank($b['sectionKey']));

        return $groups;
    }

    /**
     * Full, RBAC-agnostic list of every menu group for the visibility-config UI
     * and for validating the hidden-groups setting. Order = definition order.
     *
     * @return array<int, array{key:string, heading:string, icon:string, section:?string, sectionKey:?string, superOnly:bool, protected:bool}>
     */
    public static function groupCatalog(): array
    {
        return array_map(static fn (array $g): array => [
            'key' => $g['key'],
            'heading' => $g['heading'],
            'icon' => $g['icon'],
            'section' => self::SECTIONS[$g['section']] ?? null,
            'sectionKey' => $g['section'] ?? null,
            'superOnly' => $g['superOnly'] ?? false,
            'protected' => \in_array($g['key'], self::PROTECTED_GROUP_KEYS, true),
        ], self::definition());
    }

    /**
     * Group keys that may be toggled by the global menu-visibility setting
     * (everything except the protected groups).
     *
     * @return array<int, string>
     */
    public static function toggleableGroupKeys(): array
    {
        return array_values(array_filter(
            array_map(static fn (array $g): string => $g['key'], self::definition()),
            static fn (string $key): bool => ! \in_array($key, self::PROTECTED_GROUP_KEYS, true),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definition(): array
    {
        return [

            // ──────────────────────────────────────────────────────────────
            // 1. TỔNG QUAN — hub tất cả module + dashboard công việc
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'overview',
                'section' => 'execution',
                'heading' => 'Tổng quan',
                'icon' => 'dashboard',
                'items' => [
                    [
                        'label' => 'Tổng quan hệ thống',
                        'icon' => 'overview',
                        'href' => '/dashboard',
                        'status' => 'live',
                    ],
                    [
                        'label' => 'Dashboard Công Việc',
                        'icon' => 'projects',
                        'href' => '/work',
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
                'section' => 'execution',
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
                'section' => 'technology',
                'heading' => 'Trung tâm Công Nghệ',
                'icon' => 'rocket',
                'items' => array_values(array_filter([
                    // Tạm ẩn khi landing không public (chỉ còn /demo_1, không gắn sidebar).
                    config('va.congnghe_landing_public') ? [
                        'label' => 'Landing Công Nghệ',
                        'icon' => 'rocket',
                        'href' => '/congnghe',
                        'status' => 'live',
                    ] : null,
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
                ])),
            ],

            // ──────────────────────────────────────────────────────────────
            // 3. CÔNG VIỆC & DỰ ÁN — projects, blockers
            //    Sprints / tasks / board are accessed inside a project page,
            //    so they are not top-level nav items here.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'projects',
                'section' => 'execution',
                'heading' => 'Công việc & Dự án',
                'icon' => 'projects',
                'items' => [
                    [
                        'label' => 'Việc của tôi',
                        'icon' => 'calendar-clock',
                        'href' => '/my-work',
                        'status' => 'live',
                        'roles' => ['admin', 'lead', 'member'],
                    ],
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
                'section' => 'execution',
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
            // 5. TRI THỨC & NỘI DUNG — knowledge base
            //    Collapsed by default to keep the sidebar clean.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'knowledge',
                'section' => 'people',
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
                'section' => 'execution',
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
                ],
            ],

            // ──────────────────────────────────────────────────────────────
            // 8b. HỢP ĐỒNG (CLM) — vòng đời hợp đồng, NCC, gia hạn, chi phí
            //     Admin/Lead quản lý; Viewer chỉ xem dashboard & báo cáo.
            // ──────────────────────────────────────────────────────────────
            [
                'key' => 'contracts',
                'section' => 'execution',
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
                'section' => 'execution',
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
                'section' => 'system',
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
                'section' => 'system',
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
                    [
                        'label' => 'Nhật ký truy vết',
                        'icon' => 'shield',
                        'href' => '/audit',
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
                'section' => 'system',
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
                        'label' => 'Tùy chỉnh menu',
                        'icon' => 'columns',
                        'href' => '/settings/menu',
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
