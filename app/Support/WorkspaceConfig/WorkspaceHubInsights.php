<?php

namespace App\Support\WorkspaceConfig;

/**
 * Builds operational insight cards for the workspace-config hub.
 */
class WorkspaceHubInsights
{
    /**
     * @param  list<array<string, mixed>>  $workspaces
     * @return list<array{
     *     code: string,
     *     level: string,
     *     title: string,
     *     message: string,
     *     action: string,
     *     department_codes: list<string>
     * }>
     */
    public function build(array $workspaces, bool $canManage): array
    {
        $insights = [];

        $missingWithCriteria = array_values(array_filter(
            $workspaces,
            static fn (array $w): bool => ($w['status'] ?? '') === 'missing'
                && ($w['has_criteria'] ?? false) === true,
        ));
        if ($missingWithCriteria !== [] && $canManage) {
            $codes = array_map(static fn (array $w): string => $w['department_code'], $missingWithCriteria);
            $insights[] = [
                'code' => 'has_criteria_missing_profile',
                'level' => 'warning',
                'title' => 'Có tiêu chí nhưng chưa kích hoạt workspace',
                'message' => count($codes).' phòng ban đã có bộ tiêu chí đánh giá nhưng chưa có profile workspace.',
                'action' => 'bulk_ensure',
                'department_codes' => $codes,
            ];
        }

        $emptyActive = array_values(array_filter(
            $workspaces,
            static fn (array $w): bool => ($w['status'] ?? '') === 'active'
                && ($w['readiness']['key'] ?? '') === 'empty',
        ));
        if ($emptyActive !== []) {
            $codes = array_map(static fn (array $w): string => $w['department_code'], $emptyActive);
            $insights[] = [
                'code' => 'empty_active',
                'level' => 'info',
                'title' => 'Workspace đang dùng nhưng chưa có nội dung',
                'message' => count($codes).' phòng ban đã kích hoạt nhưng chưa cấu hình module nào (ví dụ tiêu chí đánh giá).',
                'action' => 'filter_empty_active',
                'department_codes' => $codes,
            ];
        }

        $partial = array_values(array_filter(
            $workspaces,
            static fn (array $w): bool => ($w['readiness']['key'] ?? '') === 'partial',
        ));
        if ($partial !== []) {
            $codes = array_map(static fn (array $w): string => $w['department_code'], $partial);
            $insights[] = [
                'code' => 'partial_coverage',
                'level' => 'info',
                'title' => 'Đang cấu hình dở',
                'message' => count($codes).' phòng ban mới hoàn thiện một phần module live.',
                'action' => 'filter_partial',
                'department_codes' => $codes,
            ];
        }

        return $insights;
    }

    /**
     * @param  list<array<string, mixed>>  $workspaces
     * @param  list<array{key: string, label: string}>  $moduleHeaders
     * @return array{modules: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    public function coverage(array $workspaces, array $moduleHeaders): array
    {
        $rows = [];
        foreach ($workspaces as $ws) {
            $cells = [];
            foreach ($moduleHeaders as $mod) {
                $key = $mod['key'];
                $snap = null;
                foreach ($ws['modules'] ?? [] as $m) {
                    if (($m['key'] ?? '') === $key) {
                        $snap = $m;
                        break;
                    }
                }
                $cells[$key] = [
                    'configured' => (bool) ($snap['configured'] ?? false),
                    'count' => $snap['count'] ?? null,
                ];
            }
            $rows[] = [
                'department_code' => $ws['department_code'],
                'department_name' => $ws['department_name'],
                'status' => $ws['status'],
                'status_label' => $ws['status_label'],
                'readiness' => $ws['readiness'] ?? null,
                'cells' => $cells,
            ];
        }

        return [
            'modules' => $moduleHeaders,
            'rows' => $rows,
        ];
    }
}
