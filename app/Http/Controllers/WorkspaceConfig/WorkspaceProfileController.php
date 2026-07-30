<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use App\Support\WorkspaceConfig\WorkspaceProfileProvisioner;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $modules = array_map(function (array $item) use ($dept): array {
            $item['href'] = WorkspaceConfigCatalog::hrefForDepartment($item, $dept['code']);

            return $item;
        }, WorkspaceConfigCatalog::forUser($user));

        return Inertia::render('WorkspaceConfig/Workspace/Show', [
            'workspace' => [
                'department_code' => $dept['code'],
                'department_name' => $dept['name'],
                'local_department_id' => $dept['local_department_id'],
                'source' => $dept['source'] ?? 'directory',
                'profile_id' => $profile?->id,
                'status' => $profile?->status?->value ?? 'missing',
                'status_label' => $profile?->status?->label() ?? 'Chưa cấu hình',
                'notes' => $profile?->notes,
                'criteria_count' => $criteriaCount,
                'criteria_general' => EvaluationCriterion::query()->general()->count(),
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
}
