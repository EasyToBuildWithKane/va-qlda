<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub for /workspace-config — department workspace cards.
 */
class WorkspaceConfigController extends Controller
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
        private readonly WorkspaceScopeResolver $scope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->scope->canViewHub($user), 403);

        $canManage = $this->scope->canManageAll($user);
        $ownCode = $this->scope->ownDepartmentCode($user);

        $profiles = WorkspaceProfile::query()
            ->notArchived()
            ->get()
            ->keyBy(fn (WorkspaceProfile $p) => strtolower($p->department_code));

        $criteriaCounts = EvaluationCriterion::query()
            ->selectRaw('department_code, COUNT(*) as aggregate')
            ->where('scope', EvaluationCriterionScope::Department)
            ->whereNotNull('department_code')
            ->groupBy('department_code')
            ->pluck('aggregate', 'department_code');

        $generalCriteria = EvaluationCriterion::query()->general()->count();

        $directory = $canManage
            ? $this->departments->all()
            : array_values(array_filter(
                $this->departments->all(),
                static fn (array $d): bool => $ownCode !== null
                    && strcasecmp($d['code'], $ownCode) === 0,
            ));

        $workspaces = [];
        foreach ($directory as $dept) {
            $key = strtolower($dept['code']);
            /** @var WorkspaceProfile|null $profile */
            $profile = $profiles->get($key);
            $criteriaCount = (int) ($criteriaCounts[$dept['code']] ?? 0);
            // Match case-insensitive for count keys
            if ($criteriaCount === 0) {
                foreach ($criteriaCounts as $code => $count) {
                    if (strcasecmp((string) $code, $dept['code']) === 0) {
                        $criteriaCount = (int) $count;
                        break;
                    }
                }
            }

            $status = $profile?->status?->value ?? 'missing';
            $workspaces[] = [
                'department_code' => $dept['code'],
                'department_name' => $dept['name'],
                'local_department_id' => $dept['local_department_id'],
                'source' => $dept['source'] ?? 'directory',
                'profile_id' => $profile?->id,
                'status' => $status,
                'status_label' => $profile?->status?->label() ?? 'Chưa cấu hình',
                'criteria_count' => $criteriaCount,
                'modules_live' => count(array_filter(
                    WorkspaceConfigCatalog::forUser($user),
                    static fn (array $i): bool => ($i['status'] ?? '') === 'live',
                )),
                'href' => '/workspace-config/w/'.rawurlencode($dept['code']),
                'can_ensure' => $canManage && $profile === null,
            ];
        }

        usort($workspaces, static function (array $a, array $b): int {
            $rank = static fn (string $s): int => match ($s) {
                'active' => 0,
                'draft' => 1,
                'missing' => 2,
                default => 3,
            };

            return ($rank($a['status']) <=> $rank($b['status']))
                ?: strcasecmp($a['department_name'], $b['department_name']);
        });

        $configured = count(array_filter($workspaces, static fn (array $w): bool => in_array($w['status'], ['active', 'draft'], true)));
        $active = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'active'));
        $draft = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'draft'));
        $missing = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'missing'));

        return Inertia::render('WorkspaceConfig/Hub', [
            'workspaces' => $workspaces,
            'summary' => [
                'total' => count($workspaces),
                'configured' => $configured,
                'active' => $active,
                'draft' => $draft,
                'missing' => $missing,
                'criteria_total' => EvaluationCriterion::query()->count(),
                'criteria_general' => $generalCriteria,
            ],
            'viewer' => [
                'is_super_admin' => $user->isSuperAdmin(),
                'can_manage' => $canManage,
                'own_department_code' => $ownCode,
            ],
            'statusOptions' => array_merge(
                WorkspaceProfileStatus::options(),
                [['value' => 'missing', 'label' => 'Chưa cấu hình']],
            ),
        ]);
    }
}
