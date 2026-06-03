<?php

namespace App\Support;

use App\Models\SystemAccount;

/**
 * Builds the sidebar menu as a flat list of collapsible groups:
 *
 *   Nhóm (group, collapsible)  →  Mục (item, link)
 *
 * Each group is filtered by the account role: items without a `roles` key are
 * visible to everyone; a group with no visible item is dropped. Every href is
 * listed once (no duplicates); links not built yet point to "#" and get wired
 * up as each stage lands.
 *
 * Each item carries a `status` flag so the sidebar can show, at a glance, which
 * module is in which state. Flip these here as modules move through their
 * lifecycle — the sidebar styling and the status legend read from it directly:
 *
 *   live        — đang hoạt động (hoàn thiện, dùng được)
 *   dev         — đang phát triển
 *   maintenance — đang bảo trì (tạm khoá / có thể chập chờn)
 *   planned     — sắp ra mắt (href = '#')
 */
class Navigation
{
    /**
     * @return array<int, array{heading:string, icon:string, items:array<int, array<string, mixed>>}>
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

            // Default status: unbuilt links (#) are "planned", everything else "live".
            $items = array_map(function (array $item): array {
                $item['status'] ??= $item['href'] === '#' ? 'planned' : 'live';

                return $item;
            }, $items);

            $groups[] = [
                'heading' => $group['heading'],
                'icon' => $group['icon'],
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
            ['heading' => 'Tổng quan', 'icon' => 'dashboard', 'items' => [
                ['label' => 'Bảng điều khiển', 'icon' => 'overview', 'href' => '/dashboard', 'status' => 'live', 'roles' => ['admin', 'lead', 'viewer']],
                ['label' => 'Bảng điều khiển nhóm', 'icon' => 'team-dashboard', 'href' => '#', 'status' => 'planned'],
            ]],
            ['heading' => 'Báo cáo hằng ngày', 'icon' => 'daily', 'items' => [
                ['label' => 'Hôm nay', 'icon' => 'report-today', 'href' => '/daily-reports/today', 'status' => 'live', 'roles' => ['admin', 'lead', 'member']],
                ['label' => 'Lịch sử', 'icon' => 'report-history', 'href' => '/daily-reports', 'status' => 'live'],
                ['label' => 'Chờ duyệt', 'icon' => 'review-reports', 'href' => '/daily-reports/review', 'status' => 'live', 'roles' => ['admin', 'lead']],
                ['label' => 'Vướng mắc', 'icon' => 'blockers', 'href' => '/blockers', 'status' => 'live'],
                ['label' => 'Đánh giá tuần', 'icon' => 'weekly', 'href' => '#', 'status' => 'planned', 'roles' => ['admin', 'lead']],
            ]],
            ['heading' => 'Dự án', 'icon' => 'projects', 'items' => [
                ['label' => 'Tất cả dự án', 'icon' => 'all-projects', 'href' => '/projects', 'status' => 'live'],
                ['label' => 'Phòng ban', 'icon' => 'department', 'href' => '/departments', 'status' => 'live', 'roles' => ['admin', 'lead']],
            ]],
            ['heading' => 'Chất lượng & Phản hồi', 'icon' => 'meeting', 'items' => [
                ['label' => 'Quản lý lỗi (Bug)', 'icon' => 'bug', 'href' => '/bugs', 'status' => 'live'],
                ['label' => 'Theo dõi phản hồi', 'icon' => 'feedback', 'href' => '/feedback', 'status' => 'live'],
                ['label' => 'Hạng mục hành động', 'icon' => 'action-items', 'href' => '#', 'status' => 'planned'],
            ]],
            ['heading' => 'Hiệu suất & Tri thức', 'icon' => 'performance', 'items' => [
                ['label' => 'Đánh giá nhóm', 'icon' => 'team-eval', 'href' => '#', 'status' => 'planned', 'roles' => ['admin', 'lead']],
                ['label' => 'Hồ sơ thành viên', 'icon' => 'member-profiles', 'href' => '#', 'status' => 'planned'],
                ['label' => 'Bảng xếp hạng', 'icon' => 'leaderboard', 'href' => '#', 'status' => 'planned'],
                ['label' => 'Cơ sở tri thức', 'icon' => 'knowledge', 'href' => '#', 'status' => 'planned'],
            ]],
            ['heading' => 'Hệ thống', 'icon' => 'settings', 'items' => [
                ['label' => 'Quản lý tài khoản AI', 'icon' => 'account', 'href' => '/ai-accounts', 'status' => 'live'],
                ['label' => 'Báo cáo chi phí AI', 'icon' => 'performance', 'href' => '/ai-accounts/cost-report', 'status' => 'live'],
                ['label' => 'Quản lý thông báo', 'icon' => 'notifications', 'href' => '/notifications/manage', 'status' => 'live', 'roles' => ['admin']],
                ['label' => 'Cấu hình hệ thống', 'icon' => 'system-config', 'href' => '#', 'status' => 'planned', 'roles' => ['admin']],
            ]],
        ];
    }
}
