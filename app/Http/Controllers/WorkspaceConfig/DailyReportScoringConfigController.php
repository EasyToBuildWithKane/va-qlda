<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceConfig\UpdateDailyReportScoringConfigRequest;
use App\Models\DailyReport\DailyReportScoringConfig;
use App\Support\DailyReport\DailyReportScoringResolver;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportScoringConfigController extends Controller
{
    public function __construct(
        private readonly WorkspaceScopeResolver $scope,
        private readonly HrmDepartmentDirectory $departments,
        private readonly DailyReportScoringResolver $resolver,
    ) {}

    public function edit(Request $request): Response
    {
        $this->authorize('viewAny', DailyReportScoringConfig::class);

        $user = $request->user();
        $canManageAll = $this->scope->canManageAll($user)
            || $user->allows('workspace.daily_report_scoring.manage')
            || $user->allows('workspace.daily_report_scoring.view');
        $forcedDept = $canManageAll ? null : $this->scope->ownDepartmentCode($user);

        if (! $canManageAll) {
            abort_if($forcedDept === null, 403);
        }

        $requested = trim((string) $request->query('department_code', ''));
        if ($forcedDept !== null) {
            if ($requested !== '' && strcasecmp($requested, $forcedDept) !== 0) {
                abort(403);
            }
            $departmentCode = $forcedDept;
        } else {
            $departmentCode = $requested !== '' ? $requested : null;
        }

        $directory = $this->departments->all();
        $deptRow = $departmentCode !== null ? $this->departments->findByCode($departmentCode) : null;

        $config = $departmentCode !== null
            ? DailyReportScoringConfig::query()->forDepartment($departmentCode)->first()
            : null;

        $rubric = $departmentCode !== null
            ? $this->resolver->forDepartmentCode($departmentCode, $deptRow['name'] ?? null)
            : [
                ...$this->resolver->systemDefaultsPayload(),
                'source' => DailyReportScoringResolver::SOURCE_SYSTEM,
                'department_code' => null,
                'department_name' => null,
                'config_id' => null,
            ];

        $canManage = $user->allows('workspace.daily_report_scoring.manage');

        return Inertia::render('WorkspaceConfig/DailyReportScoring/Edit', [
            'departments' => $directory,
            'departmentCode' => $departmentCode,
            'departmentName' => $deptRow['name'] ?? ($config?->department_name),
            'config' => $config ? [
                'id' => $config->id,
                'department_code' => $config->department_code,
                'department_name' => $config->department_name,
                'local_department_id' => $config->local_department_id,
                'weights' => $config->weights,
                'kaizen_bonus_max' => (float) $config->kaizen_bonus_max,
                'status' => $config->status,
                'updated_at' => $config->updated_at?->toIso8601String(),
            ] : null,
            'rubric' => $rubric,
            'systemDefaults' => $this->resolver->systemDefaultsPayload(),
            'dimensionLabels' => [
                'task_completion' => 'Hoàn thành',
                'skill_score' => 'Kỹ năng',
                'attitude_score' => 'Thái độ',
                'expertise_score' => 'Chuyên môn',
            ],
            'viewer' => [
                'can_manage_all' => $canManageAll,
                'can_manage' => $canManage,
                'own_department_code' => $this->scope->ownDepartmentCode($user),
                'forced_department_code' => $forcedDept,
            ],
            'can' => [
                'manage' => $canManage,
            ],
        ]);
    }

    public function update(UpdateDailyReportScoringConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $code = trim((string) $data['department_code']);

        abort_unless($this->scope->canAccess($user, $code)
            || $user->allows('workspace.daily_report_scoring.manage'), 403);

        $dept = $this->departments->findByCode($code);

        $weights = [
            'task_completion' => (float) $data['weights']['task_completion'],
            'skill_score' => (float) $data['weights']['skill_score'],
            'attitude_score' => (float) $data['weights']['attitude_score'],
            'expertise_score' => (float) $data['weights']['expertise_score'],
        ];

        $config = DailyReportScoringConfig::query()->withTrashed()->forDepartment($code)->first();

        $payload = [
            'department_code' => $dept['code'] ?? $code,
            'department_name' => $data['department_name']
                ?? $dept['name']
                ?? $code,
            'local_department_id' => $data['local_department_id']
                ?? $dept['local_department_id']
                ?? null,
            'weights' => $weights,
            'kaizen_bonus_max' => (float) $data['kaizen_bonus_max'],
            'status' => $data['status'] ?? DailyReportScoringConfig::STATUS_ACTIVE,
            'updated_by' => $user->id,
        ];

        if ($config !== null) {
            if ($config->trashed()) {
                $config->restore();
            }
            $config->update($payload);
        } else {
            $config = DailyReportScoringConfig::query()->create($payload);
        }

        activity('workspace')
            ->performedOn($config)
            ->event('daily_report_scoring_updated')
            ->withProperties([
                'department_code' => $config->department_code,
                'weights' => $config->weights,
                'kaizen_bonus_max' => $config->kaizen_bonus_max,
            ])
            ->log('Cập nhật trọng số báo cáo ngày theo phòng ban');

        return redirect()
            ->route('workspace.daily-report-scoring.edit', [
                'department_code' => $config->department_code,
            ])
            ->with('success', 'Đã lưu trọng số chấm báo cáo ngày cho phòng ban.');
    }
}
