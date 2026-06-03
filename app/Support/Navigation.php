<?php

namespace App\Support;

use App\Models\SystemAccount;

/**
 * Builds the sidebar menu as collapsible groups:
 *
 *   Nhóm (group, collapsible)  →  Mục (item, link)
 *
 * Live modules sit in four top-level groups; every unbuilt module is listed
 * once under «Sắp ra mắt» (variant upcoming, collapsed by default).
 *
 * Each item carries a `status` flag for sidebar styling:
 *
 *   live        — đang hoạt động
 *   dev         — đang phát triển
 *   maintenance — đang bảo trì
 *   planned     — sắp ra mắt (href = '#')
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
                fn (array $item) => ! isset($item['roles']) || in_array($role, $item['roles'], true),
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

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definition(): array
    {
        return [
            [
                'key' => 'overview',
                'heading' => 'Tổng quan',
                'icon' => 'dashboard',
                'items' => [
                    ['label' => 'Bảng điều khiển', 'icon' => 'overview', 'href' => '/dashboard', 'status' => 'live', 'roles' => ['admin', 'lead', 'viewer']],
                ],
            ],
            [
                'key' => 'work',
                'heading' => 'Báo cáo & Dự án',
                'icon' => 'daily',
                'items' => [
                    ['label' => 'Báo cáo hôm nay', 'icon' => 'report-today', 'href' => '/daily-reports/today', 'status' => 'live', 'roles' => ['admin', 'lead', 'member']],
                    ['label' => 'Lịch sử báo cáo', 'icon' => 'report-history', 'href' => '/daily-reports', 'status' => 'live'],
                    ['label' => 'Chờ duyệt', 'icon' => 'review-reports', 'href' => '/daily-reports/review', 'status' => 'live', 'roles' => ['admin', 'lead']],
                    ['label' => 'Tất cả dự án', 'icon' => 'all-projects', 'href' => '/projects', 'status' => 'live'],
                    ['label' => 'Vướng mắc', 'icon' => 'blockers', 'href' => '/blockers', 'status' => 'live'],
                    ['label' => 'Phòng ban', 'icon' => 'department', 'href' => '/departments', 'status' => 'live', 'roles' => ['admin', 'lead']],
                ],
            ],
            [
                'key' => 'quality',
                'heading' => 'Chất lượng',
                'icon' => 'bug',
                'items' => [
                    ['label' => 'Quản lý lỗi', 'icon' => 'bug', 'href' => '/bugs', 'status' => 'live'],
                    ['label' => 'Theo dõi phản hồi', 'icon' => 'feedback', 'href' => '/feedback', 'status' => 'live'],
                ],
            ],
            [
                'key' => 'admin',
                'heading' => 'Quản trị',
                'icon' => 'settings',
                'items' => [
                    ['label' => 'Tài khoản AI', 'icon' => 'account', 'href' => '/ai-accounts', 'status' => 'live'],
                    ['label' => 'Chi phí AI', 'icon' => 'cost', 'href' => '/ai-accounts/cost-report', 'status' => 'live'],
                    ['label' => 'Thông báo', 'icon' => 'notifications', 'href' => '/notifications/manage', 'status' => 'live', 'roles' => ['admin']],
                ],
            ],
            [
                'key' => 'upcoming',
                'heading' => 'Sắp ra mắt',
                'icon' => 'rocket',
                'variant' => 'upcoming',
                'defaultCollapsed' => true,
                'items' => [
                    ['label' => 'Bảng điều khiển nhóm', 'icon' => 'team-dashboard', 'href' => '#', 'status' => 'planned'],
                    ['label' => 'Đánh giá tuần', 'icon' => 'weekly', 'href' => '#', 'status' => 'planned', 'roles' => ['admin', 'lead']],
                    ['label' => 'Hạng mục hành động', 'icon' => 'action-items', 'href' => '#', 'status' => 'planned'],
                    ['label' => 'Đánh giá nhóm', 'icon' => 'team-eval', 'href' => '#', 'status' => 'planned', 'roles' => ['admin', 'lead']],
                    ['label' => 'Hồ sơ thành viên', 'icon' => 'member-profiles', 'href' => '#', 'status' => 'planned'],
                    ['label' => 'Bảng xếp hạng', 'icon' => 'leaderboard', 'href' => '#', 'status' => 'planned'],
                    ['label' => 'Cơ sở tri thức', 'icon' => 'knowledge', 'href' => '#', 'status' => 'planned'],
                    ['label' => 'Cấu hình hệ thống', 'icon' => 'system-config', 'href' => '#', 'status' => 'planned', 'roles' => ['admin']],
                ],
            ],
        ];
    }
}
