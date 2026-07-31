<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\WorkspaceProfileStatus;

/**
 * Builds department workspace cards for the /workspace-config hub.
 */
class WorkspaceHubAssembler
{
    /** @var list<string> */
    private const ACCENT_TONES = ['brand', 'emerald', 'sky', 'violet', 'amber', 'rose'];

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     local_department_id?: int|null,
     *     source?: string
     * }  $dept
     * @param  array<string, int>  $criteriaCounts  keyed by department_code (any case)
     * @return array<string, mixed>
     */
    public function card(
        array $dept,
        ?WorkspaceProfile $profile,
        array $criteriaCounts,
        SystemAccount $user,
        bool $canManage,
        int $generalCriteria,
    ): array {
        $code = $dept['code'];
        $criteriaCount = $this->criteriaCountFor($code, $criteriaCounts);

        $profileStatus = $profile?->status?->value ?? 'missing';
        $profileLabel = match ($profileStatus) {
            WorkspaceProfileStatus::Active->value => WorkspaceProfileStatus::Active->label(),
            WorkspaceProfileStatus::Draft->value => WorkspaceProfileStatus::Draft->label(),
            WorkspaceProfileStatus::Archived->value => WorkspaceProfileStatus::Archived->label(),
            default => 'Chưa kích hoạt',
        };

        $modules = WorkspaceConfigCatalog::forUser($user);
        $liveModules = array_values(array_filter(
            $modules,
            static fn (array $i): bool => ($i['status'] ?? '') === 'live',
        ));
        $plannedModules = count(array_filter(
            $modules,
            static fn (array $i): bool => ($i['status'] ?? '') === 'planned',
        ));

        $moduleSnapshots = [];
        $configuredLive = 0;
        foreach ($liveModules as $mod) {
            $key = (string) ($mod['key'] ?? '');
            $configured = match ($key) {
                'evaluation' => $criteriaCount > 0,
                default => false,
            };
            if ($configured) {
                $configuredLive++;
            }
            $moduleSnapshots[] = [
                'key' => $key,
                'label' => $mod['label'] ?? $key,
                'icon' => $mod['icon'] ?? 'system-config',
                'tone' => $mod['tone'] ?? 'slate',
                'status' => $mod['status'] ?? 'planned',
                'configured' => $configured,
                'count' => $key === 'evaluation' ? $criteriaCount : null,
                'href' => WorkspaceConfigCatalog::hrefForDepartment($mod, $code),
            ];
        }

        $liveCount = count($liveModules);
        $readinessPct = $liveCount > 0
            ? (int) round(($configuredLive / $liveCount) * 100)
            : 0;
        $readinessKey = match (true) {
            $readinessPct >= 100 => 'ready',
            $readinessPct > 0 => 'partial',
            default => 'empty',
        };
        $readinessLabel = match ($readinessKey) {
            'ready' => 'Đã sẵn sàng',
            'partial' => 'Đang cấu hình',
            default => 'Chưa có nội dung',
        };

        return [
            'department_code' => $code,
            'department_name' => $dept['name'],
            'local_department_id' => $dept['local_department_id'] ?? null,
            'source' => $dept['source'] ?? 'directory',
            'source_label' => ($dept['source'] ?? 'directory') === 'hrm' ? 'HRM' : 'Danh mục nội bộ',
            'profile_id' => $profile?->id,
            'status' => $profileStatus,
            'status_label' => $profileLabel,
            'accent' => $this->accentTone($code),
            'criteria_count' => $criteriaCount,
            'criteria_general' => $generalCriteria,
            'has_criteria' => $criteriaCount > 0,
            'modules_live' => $liveCount,
            'modules_planned' => $plannedModules,
            'modules_configured' => $configuredLive,
            'modules' => $moduleSnapshots,
            'readiness' => [
                'key' => $readinessKey,
                'label' => $readinessLabel,
                'percent' => $readinessPct,
                'configured' => $configuredLive,
                'total' => $liveCount,
            ],
            'notes' => $profile?->notes,
            'updated_at' => $profile?->updated_at?->toIso8601String(),
            'created_at' => $profile?->created_at?->toIso8601String(),
            'href' => '/workspace-config/w/'.rawurlencode($code),
            'evaluation_href' => '/workspace-config/evaluation?department_code='.rawurlencode($code).'&scope=department',
            'can_ensure' => $canManage && $profile === null,
            'can_update' => $canManage && $profile !== null,
            'checklist' => WorkspaceConfigCatalog::checklistForDepartment($user, [
                'criteria_count' => $criteriaCount,
                'profile_status' => $profileStatus,
                'department_code' => $code,
            ]),
        ];
    }

    /**
     * @param  array<string, int>  $criteriaCounts
     */
    private function criteriaCountFor(string $code, array $criteriaCounts): int
    {
        $direct = $criteriaCounts[$code] ?? null;
        if ($direct !== null) {
            return (int) $direct;
        }

        foreach ($criteriaCounts as $key => $count) {
            if (strcasecmp((string) $key, $code) === 0) {
                return (int) $count;
            }
        }

        return 0;
    }

    private function accentTone(string $code): string
    {
        $idx = (int) (crc32(strtolower($code)) % count(self::ACCENT_TONES));

        return self::ACCENT_TONES[$idx];
    }
}
