<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;

/**
 * Registry of workspace-config domain modules (shown on department workspace show).
 */
class WorkspaceConfigCatalog
{
    /**
     * @return array<int, array<string, mixed>>
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
                'empty_cta' => 'Thêm tiêu chí phòng ban',
                'configured_cta' => 'Quản lý tiêu chí',
                'onboard_steps' => [
                    [
                        'key' => 'has_department_criteria',
                        'label' => 'Có ít nhất một tiêu chí theo phòng ban',
                        'done_hint' => 'Đã có tiêu chí phòng ban',
                    ],
                ],
            ],
            [
                'key' => 'daily_report_scoring',
                'label' => 'Trọng số báo cáo ngày',
                'description' => 'Trọng số 4 tiêu chí chính và điểm Kaizen tối đa khi chấm báo cáo ngày theo phòng ban.',
                'href' => '/workspace-config/daily-report-scoring',
                'icon' => 'daily',
                'tone' => 'emerald',
                'status' => 'live',
                'permission' => 'workspace.daily_report_scoring.view',
                'applies_to' => 'department',
                'empty_cta' => 'Cấu hình trọng số',
                'configured_cta' => 'Chỉnh trọng số',
                'onboard_steps' => [
                    [
                        'key' => 'has_scoring_config',
                        'label' => 'Đã cấu hình trọng số chấm báo cáo ngày',
                        'done_hint' => 'Đã có trọng số phòng ban',
                    ],
                ],
            ],
            [
                'key' => 'evaluation_templates',
                'label' => 'Danh sách mẫu đánh giá',
                'description' => 'Gói tiêu chí theo vị trí — nhập/xuất Excel, nhân bản mẫu.',
                'href' => '/workspace-config/evaluation-templates',
                'icon' => 'clipboard-list',
                'tone' => 'rose',
                'status' => 'live',
                'permission' => 'workspace.evaluation.view',
                'applies_to' => 'global',
                'empty_cta' => 'Thêm mẫu đánh giá',
                'configured_cta' => 'Quản lý mẫu',
                'onboard_steps' => [
                    [
                        'key' => 'has_templates',
                        'label' => 'Có ít nhất một mẫu đánh giá',
                        'done_hint' => 'Đã có mẫu đánh giá',
                    ],
                ],
            ],
            [
                'key' => 'evaluation_forms',
                'label' => 'Danh sách phiếu đánh giá',
                'description' => 'Phiếu đánh giá theo mẫu, kỳ và danh sách nhân sự — cấu hình hội đồng & tiêu chí.',
                'href' => '/workspace-config/evaluation-forms',
                'icon' => 'clipboard-list',
                'tone' => 'sky',
                'status' => 'live',
                'permission' => 'workspace.evaluation.view',
                'applies_to' => 'global',
                'empty_cta' => 'Tạo phiếu đánh giá',
                'configured_cta' => 'Quản lý phiếu',
                'onboard_steps' => [
                    [
                        'key' => 'has_forms',
                        'label' => 'Có ít nhất một phiếu đánh giá',
                        'done_hint' => 'Đã có phiếu đánh giá',
                    ],
                ],
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
                'empty_cta' => 'Sắp ra mắt',
                'configured_cta' => 'Sắp ra mắt',
                'onboard_steps' => [
                    [
                        'key' => 'planned',
                        'label' => 'Tạo mẫu thông báo (sắp ra mắt)',
                        'done_hint' => 'Chưa triển khai',
                    ],
                ],
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
                if (in_array($permission, ['workspace.evaluation.view', 'workspace.daily_report_scoring.view'], true)
                    && ($account->allows('workspace.hub.view')
                        || $account->allows('workspace.evaluation.manage')
                        || $account->allows('workspace.daily_report_scoring.manage'))) {
                    return true;
                }

                $manage = str_replace('.view', '.manage', $permission);

                return $manage !== $permission && $account->allows($manage);
            },
        ));
    }

    /**
     * Live modules only (for coverage matrix headers).
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function liveModuleHeaders(SystemAccount $account): array
    {
        return array_values(array_map(
            static fn (array $m): array => [
                'key' => (string) $m['key'],
                'label' => (string) $m['label'],
            ],
            array_filter(
                self::forUser($account),
                static fn (array $i): bool => ($i['status'] ?? '') === 'live',
            ),
        ));
    }

    /**
     * Build onboard checklist for a department workspace.
     *
     * @param  array<string, mixed>  $context  keys: criteria_count, profile_status, has_scoring_config
     * @return array<int, array<string, mixed>>
     */
    public static function checklistForDepartment(SystemAccount $account, array $context): array
    {
        $criteriaCount = (int) ($context['criteria_count'] ?? 0);
        $templateCount = (int) ($context['template_count'] ?? 0);
        $formCount = (int) ($context['form_count'] ?? 0);
        $hasScoringConfig = (bool) ($context['has_scoring_config'] ?? false);
        $profileStatus = (string) ($context['profile_status'] ?? 'missing');
        $items = [];

        $items[] = [
            'key' => 'profile_active',
            'module' => 'workspace',
            'label' => 'Kích hoạt workspace phòng ban',
            'done' => in_array($profileStatus, ['active', 'draft'], true),
            'planned' => false,
            'done_hint' => $profileStatus === 'active'
                ? 'Workspace đang dùng'
                : ($profileStatus === 'draft' ? 'Workspace ở trạng thái nháp' : 'Chưa kích hoạt'),
        ];

        foreach (self::forUser($account) as $mod) {
            $key = (string) ($mod['key'] ?? '');
            $planned = ($mod['status'] ?? '') === 'planned';
            foreach ($mod['onboard_steps'] ?? [] as $step) {
                $stepKey = (string) ($step['key'] ?? '');
                $done = false;
                if (! $planned) {
                    $done = match ($stepKey) {
                        'has_department_criteria' => $criteriaCount > 0,
                        'has_templates' => $templateCount > 0,
                        'has_forms' => $formCount > 0,
                        'has_scoring_config' => $hasScoringConfig,
                        default => false,
                    };
                }
                $items[] = [
                    'key' => $key.'.'.$stepKey,
                    'module' => $key,
                    'label' => (string) ($step['label'] ?? $stepKey),
                    'done' => $done,
                    'planned' => $planned,
                    'done_hint' => $planned
                        ? 'Sắp ra mắt'
                        : (string) ($step['done_hint'] ?? ($done ? 'Hoàn tất' : 'Chưa xong')),
                    'href' => $planned ? '#' : self::hrefForDepartment($mod, (string) ($context['department_code'] ?? '')),
                ];
            }
        }

        return $items;
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
