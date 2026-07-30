<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;

/**
 * Registry of workspace-config sidebar items (hub + nav).
 *
 * Add new config domains here when shipping — keep Evaluation as a child,
 * not a sibling of the umbrella module.
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
     *     permission: string
     * }>
     */
    public static function definition(): array
    {
        return [
            [
                'key' => 'evaluation',
                'label' => 'Cấu hình đánh giá',
                'description' => 'Bộ quy tắc đánh giá theo phòng ban, mẫu điểm cộng/trừ và phiếu tiêu chí.',
                'href' => '/workspace-config/evaluation',
                'icon' => 'award',
                'tone' => 'amber',
                'status' => 'live',
                'permission' => 'workspace.evaluation.view',
            ],
            // Thêm item mới tại đây (vd. chu kỳ đánh giá, mẫu chung, …).
        ];
    }

    /**
     * Items the account may open (permission + reserved keys via allows()).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(SystemAccount $account): array
    {
        return array_values(array_filter(
            self::definition(),
            static fn (array $item): bool => $account->allows($item['permission'])
                || $account->allows(str_replace('.view', '.manage', $item['permission'])),
        ));
    }
}
