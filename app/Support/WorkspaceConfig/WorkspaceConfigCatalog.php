<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;

/**
 * Registry of workspace-config domain modules (shown on department workspace show).
 */
class WorkspaceConfigCatalog
{
    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     href: string,
     *     icon: string,
     *     tone: string,
     *     status: string,
     *     permission: string,
     *     applies_to: string
     * }>
     */
    public static function definition(): array
    {
        return [
            [
                'key' => 'evaluation',
                'label' => 'Cấu hình tiêu chí đánh giá',
                'description' => 'Danh mục tiêu chí chung và theo phòng ban — thang điểm 1–5, loại tiêu chí.',
                'href' => '/workspace-config/evaluation',
                'icon' => 'award',
                'tone' => 'amber',
                'status' => 'live',
                'permission' => 'workspace.evaluation.view',
                'applies_to' => 'department',
            ],
            [
                'key' => 'cycles',
                'label' => 'Chu kỳ đánh giá',
                'description' => 'Kỳ đánh giá, mốc mở/đóng phiếu theo phòng ban.',
                'href' => '#',
                'icon' => 'calendar',
                'tone' => 'sky',
                'status' => 'planned',
                'permission' => 'workspace.hub.view',
                'applies_to' => 'department',
            ],
            [
                'key' => 'notifications',
                'label' => 'Mẫu thông báo workspace',
                'description' => 'Mẫu nhắc đánh giá / thông báo nội bộ theo phòng ban.',
                'href' => '#',
                'icon' => 'send',
                'tone' => 'violet',
                'status' => 'planned',
                'permission' => 'workspace.hub.view',
                'applies_to' => 'department',
            ],
        ];
    }

    /**
     * Modules the account may open (permission + reserved keys via allows()).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(SystemAccount $account): array
    {
        return array_values(array_filter(
            self::definition(),
            static function (array $item) use ($account): bool {
                $permission = $item['permission'];
                if ($account->allows($permission)) {
                    return true;
                }

                // View reserved domains: hub.view is enough to open read-only module list.
                if ($permission === 'workspace.evaluation.view'
                    && ($account->allows('workspace.hub.view') || $account->allows('workspace.evaluation.manage'))) {
                    return true;
                }

                $manage = str_replace('.view', '.manage', $permission);

                return $manage !== $permission && $account->allows($manage);
            },
        ));
    }

    /**
     * Build href for a department-scoped module.
     *
     * @param  array<string, mixed>  $item
     */
    public static function hrefForDepartment(array $item, string $departmentCode): string
    {
        $href = (string) ($item['href'] ?? '#');
        if ($href === '#' || ($item['status'] ?? '') === 'planned') {
            return '#';
        }

        if (($item['applies_to'] ?? '') !== 'department') {
            return $href;
        }

        $separator = str_contains($href, '?') ? '&' : '?';

        return $href.$separator.'department_code='.rawurlencode($departmentCode).'&scope=department';
    }
}
