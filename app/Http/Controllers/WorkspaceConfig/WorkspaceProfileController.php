<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceConfig\BulkEnsureWorkspaceProfileRequest;
use App\Http\Requests\WorkspaceConfig\UpdateWorkspaceProfileRequest;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
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

        $modules = array_map(function (array $item) use ($dept, $criteriaCount): array {
            $item['href'] = WorkspaceConfigCatalog::hrefForDepartment($item, $dept['code']);
            $key = (string) ($item['key'] ?? '');
            $item['configured'] = match ($key) {
                'evaluation' => $criteriaCount > 0,
                default => false,
            };
            $item['count'] = $key === 'evaluation' ? $criteriaCount : null;
            $item['count_label'] = $key === 'evaluation'
                ? ($criteriaCount > 0 ? "{$criteriaCount} tiêu chí phòng ban" : 'Chưa có tiêu chí phòng ban')
                : null;

            return $item;
        }, WorkspaceConfigCatalog::forUser($user));

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
            'viewer' => [
                'can_manage' => $this->scope->canManageAll($user),
                'own_department_code' => $this->scope->ownDepartmentCode($user),
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
        $profile->save();

        $msg = 'Đã cập nhật workspace phòng ban.';
        if (isset($data['status'])) {
            $msg = match ($data['status']) {
                WorkspaceProfileStatus::Archived->value => 'Đã lưu trữ workspace phòng ban.',
                WorkspaceProfileStatus::Active->value => 'Đã khôi phục / kích hoạt workspace phòng ban.',
                WorkspaceProfileStatus::Draft->value => 'Đã chuyển workspace sang trạng thái nháp.',
                default => $msg,
            };
        } elseif (array_key_exists('notes', $data)) {
            $msg = 'Đã lưu ghi chú workspace.';
        }

        return back()->with('success', $msg);
    }
}
