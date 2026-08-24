<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceConfig\BulkEnsureWorkspaceProfileRequest;
use App\Http\Requests\WorkspaceConfig\UpdateWorkspaceProfileRequest;
use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\Navigation;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use App\Support\WorkspaceConfig\WorkspaceProfileProvisioner;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class WorkspaceProfileController extends Controller
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
        private readonly WorkspaceScopeResolver $scope,
        private readonly WorkspaceProfileProvisioner $provisioner,
    ) {}

    public function show(Request $request, string $departmentCode): Response
    {
        $user = $request->user();
        abort_unless($this->scope->canAccess($user, $departmentCode), 403);

        $dept = $this->departments->findByCode($departmentCode);
        abort_if($dept === null, 404);

        $profile = WorkspaceProfile::query()
            ->where('department_code', $dept['code'])
            ->first();

        $criteriaCount = EvaluationCriterion::query()
            ->where('scope', EvaluationCriterionScope::Department)
            ->where('department_code', $dept['code'])
            ->count();

        $generalCriteria = EvaluationCriterion::query()->general()->count();

        $hasScoringConfig = DailyReportScoringConfig::query()
            ->active()
            ->forDepartment($dept['code'])
            ->exists();

        $templateCount = EvaluationTemplate::query()->count();
        $formCount = EvaluationForm::query()->count();

        $modules = array_map(function (array $item) use ($dept, $criteriaCount, $hasScoringConfig, $templateCount, $formCount): array {
            $item['href'] = WorkspaceConfigCatalog::hrefForDepartment($item, $dept['code']);
            $key = (string) ($item['key'] ?? '');
            $item['configured'] = match ($key) {
                'evaluation' => $criteriaCount > 0,
                'evaluation_templates' => $templateCount > 0,
                'evaluation_forms' => $formCount > 0,
                'daily_report_scoring' => $hasScoringConfig,
                default => false,
            };
            $item['count'] = match ($key) {
                'evaluation' => $criteriaCount,
                'evaluation_templates' => $templateCount,
                'evaluation_forms' => $formCount,
                'daily_report_scoring' => $hasScoringConfig ? 1 : 0,
                default => null,
            };
            $item['count_label'] = match ($key) {
                'evaluation' => $criteriaCount > 0
                    ? "{$criteriaCount} tiêu chí phòng ban"
                    : 'Chưa có tiêu chí phòng ban',
                'evaluation_templates' => $templateCount > 0
                    ? "{$templateCount} mẫu đánh giá"
                    : 'Chưa có mẫu đánh giá',
                'evaluation_forms' => $formCount > 0
                    ? "{$formCount} phiếu đánh giá"
                    : 'Chưa có phiếu đánh giá',
                'daily_report_scoring' => $hasScoringConfig
                    ? 'Đã cấu hình trọng số'
                    : 'Dùng mặc định hệ thống',
                default => null,
            };

            return $item;
        }, WorkspaceConfigCatalog::forUser($user, $dept['code']));

        $liveModules = array_values(array_filter(
            $modules,
            static fn (array $i): bool => ($i['status'] ?? '') === 'live',
        ));
        $configuredLive = count(array_filter(
            $liveModules,
            static fn (array $i): bool => ($i['configured'] ?? false) === true,
        ));
        $liveCount = count($liveModules);
        $readinessPct = $liveCount > 0
            ? (int) round(($configuredLive / $liveCount) * 100)
            : 0;
        $readinessKey = match (true) {
            $readinessPct >= 100 => 'ready',
            $readinessPct > 0 => 'partial',
            default => 'empty',
        };

        $profileStatus = $profile?->status?->value ?? 'missing';
        $profileLabel = $profile?->status?->label() ?? 'Chưa kích hoạt';

        $checklist = WorkspaceConfigCatalog::checklistForDepartment($user, [
            'criteria_count' => $criteriaCount,
            'template_count' => $templateCount,
            'form_count' => $formCount,
            'has_scoring_config' => $hasScoringConfig,
            'profile_status' => $profileStatus,
            'department_code' => $dept['code'],
        ]);
        $checklistDone = count(array_filter(
            $checklist,
            static fn (array $s): bool => ($s['done'] ?? false) === true && ($s['planned'] ?? false) === false,
        ));
        $checklistTotal = count(array_filter(
            $checklist,
            static fn (array $s): bool => ($s['planned'] ?? false) === false,
        ));

        $navCatalog = array_values(array_filter(
            Navigation::groupCatalog(),
            static fn (array $g): bool => ! ($g['protected'] ?? false),
        ));

        $own = $this->scope->ownDepartment($user);

        return Inertia::render('WorkspaceConfig/Workspace/Show', [
            'workspace' => [
                'department_code' => $dept['code'],
                'department_name' => $dept['name'],
                'local_department_id' => $dept['local_department_id'],
                'source' => $dept['source'] ?? 'directory',
                'source_label' => ($dept['source'] ?? 'directory') === 'hrm' ? 'HRM' : 'Danh mục nội bộ',
                'profile_id' => $profile?->id,
                'status' => $profileStatus,
                'status_label' => $profileLabel,
                'notes' => $profile?->notes,
                'enabled_nav_groups' => $profile?->enabled_nav_groups,
                'criteria_count' => $criteriaCount,
                'criteria_general' => $generalCriteria,
                'has_criteria' => $criteriaCount > 0,
                'updated_at' => $profile?->updated_at?->toIso8601String(),
                'readiness' => [
                    'key' => $readinessKey,
                    'label' => match ($readinessKey) {
                        'ready' => 'Đã sẵn sàng',
                        'partial' => 'Đang cấu hình',
                        default => 'Chưa có nội dung',
                    },
                    'percent' => $readinessPct,
                    'configured' => $configuredLive,
                    'total' => $liveCount,
                ],
                'evaluation_href' => '/workspace-config/evaluation?department_code='.rawurlencode($dept['code']).'&scope=department',
            ],
            'checklist' => [
                'items' => $checklist,
                'done' => $checklistDone,
                'total' => $checklistTotal,
            ],
            'modules' => $modules,
            'navMenu' => [
                'groups' => $navCatalog,
                'enabled' => $profile?->enabled_nav_groups,
            ],
            'viewer' => [
                'can_manage' => $this->scope->canManageAll($user),
                'own_department_code' => is_array($own) && filled($own['code'] ?? null) ? (string) $own['code'] : null,
                'own_department_name' => is_array($own) && filled($own['name'] ?? null) ? (string) $own['name'] : null,
            ],
        ]);
    }

    public function ensure(Request $request, string $departmentCode): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->canManageAll($user), 403);

        try {
            $profile = $this->provisioner->ensure($departmentCode, $user, activate: true);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('workspace.profiles.show', ['departmentCode' => $profile->department_code])
            ->with('success', 'Đã kích hoạt workspace phòng ban.');
    }

    public function ensureBulk(BulkEnsureWorkspaceProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $codes = $request->validated('codes');

        $result = DB::transaction(function () use ($codes, $user) {
            return $this->provisioner->ensureMany($codes, $user, activate: true);
        });

        $done = count($result['codes']);
        if ($done === 0) {
            return back()->with('error', 'Không kích hoạt được phòng ban nào. Kiểm tra mã phòng ban trong danh mục.');
        }

        $parts = [];
        if ($result['created'] > 0) {
            $parts[] = "tạo mới {$result['created']}";
        }
        if ($result['activated'] > 0) {
            $parts[] = "cập nhật {$result['activated']}";
        }
        if ($result['skipped'] > 0) {
            $parts[] = "bỏ qua {$result['skipped']}";
        }

        return back()->with(
            'success',
            'Đã kích hoạt '.$done.' workspace'.($parts !== [] ? ' ('.implode(', ', $parts).')' : '').'.',
        );
    }

    public function update(UpdateWorkspaceProfileRequest $request, string $departmentCode): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->canAccess($user, $departmentCode), 403);

        $dept = $this->departments->findByCode($departmentCode);
        abort_if($dept === null, 404);

        $profile = WorkspaceProfile::query()
            ->where('department_code', $dept['code'])
            ->first();

        if ($profile === null) {
            return back()->with('error', 'Workspace chưa được kích hoạt. Hãy kích hoạt trước khi cập nhật.');
        }

        $data = $request->validated();
        if (array_key_exists('notes', $data)) {
            $profile->notes = $data['notes'];
        }
        if (array_key_exists('status', $data)) {
            $profile->status = WorkspaceProfileStatus::from($data['status']);
        }
        if (array_key_exists('enabled_nav_groups', $data)) {
            $profile->enabled_nav_groups = $data['enabled_nav_groups'];
        }
        $profile->save();

        $msg = 'Đã cập nhật workspace phòng ban.';
        if (isset($data['status'])) {
            $msg = match ($data['status']) {
                WorkspaceProfileStatus::Archived->value => 'Đã lưu trữ workspace phòng ban.',
                WorkspaceProfileStatus::Active->value => 'Đã khôi phục / kích hoạt workspace phòng ban.',
                WorkspaceProfileStatus::Draft->value => 'Đã chuyển workspace sang trạng thái nháp.',
                default => $msg,
            };
        } elseif (array_key_exists('enabled_nav_groups', $data)) {
            $msg = $data['enabled_nav_groups'] === null
                ? 'Đã mở toàn bộ nhóm menu sidebar cho phòng ban.'
                : 'Đã cập nhật menu sidebar phòng ban.';
        } elseif (array_key_exists('notes', $data)) {
            $msg = 'Đã lưu ghi chú workspace.';
        }

        return back()->with('success', $msg);
    }
}
