<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use App\Support\WorkspaceConfig\WorkspaceHubAssembler;
use App\Support\WorkspaceConfig\WorkspaceHubInsights;
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
        private readonly WorkspaceHubAssembler $assembler,
        private readonly WorkspaceHubInsights $insights,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->scope->canViewHub($user), 403);

        $canManage = $this->scope->canManageAll($user);
        $ownCode = $this->scope->ownDepartmentCode($user);
        $includeArchived = $canManage && $request->boolean('include_archived');

        $profiles = WorkspaceProfile::query()
            ->get()
            ->keyBy(fn (WorkspaceProfile $p) => strtolower($p->department_code));

        $criteriaCounts = EvaluationCriterion::query()
            ->selectRaw('department_code, COUNT(*) as aggregate')
            ->where('scope', EvaluationCriterionScope::Department)
            ->whereNotNull('department_code')
            ->groupBy('department_code')
            ->pluck('aggregate', 'department_code');

        $generalCriteria = EvaluationCriterion::query()->general()->count();
        $templateCount = EvaluationTemplate::query()->count();
        $formCount = EvaluationForm::query()->count();

        $scoringConfigured = DailyReportScoringConfig::query()
            ->active()
            ->pluck('department_code')
            ->mapWithKeys(static fn (string $code): array => [strtolower($code) => true])
            ->all();

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

            if ($profile?->status === WorkspaceProfileStatus::Archived && ! $includeArchived) {
                // Default hub: archived-only PB appears as chưa kích hoạt (can re-ensure).
                $profile = null;
            }

            $workspaces[] = $this->assembler->card(
                $dept,
                $profile,
                $criteriaCounts->all(),
                $user,
                $canManage,
                $generalCriteria,
                $templateCount,
                $scoringConfigured,
                $formCount,
            );
        }

        usort($workspaces, static function (array $a, array $b): int {
            $rank = static fn (string $s): int => match ($s) {
                'active' => 0,
                'draft' => 1,
                'missing' => 2,
                'archived' => 3,
                default => 4,
            };
            $readyRank = static fn (string $s): int => match ($s) {
                'ready' => 0,
                'partial' => 1,
                default => 2,
            };

            return ($rank($a['status']) <=> $rank($b['status']))
                ?: ($readyRank($a['readiness']['key'] ?? 'empty') <=> $readyRank($b['readiness']['key'] ?? 'empty'))
                ?: strcasecmp($a['department_name'], $b['department_name']);
        });

        $configured = count(array_filter($workspaces, static fn (array $w): bool => in_array($w['status'], ['active', 'draft'], true)));
        $active = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'active'));
        $draft = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'draft'));
        $missing = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'missing'));
        $archived = count(array_filter($workspaces, static fn (array $w): bool => $w['status'] === 'archived'));
        $withCriteria = count(array_filter($workspaces, static fn (array $w): bool => ($w['has_criteria'] ?? false) === true));
        $ready = count(array_filter($workspaces, static fn (array $w): bool => ($w['readiness']['key'] ?? '') === 'ready'));
        $partial = count(array_filter($workspaces, static fn (array $w): bool => ($w['readiness']['key'] ?? '') === 'partial'));

        $moduleHeaders = WorkspaceConfigCatalog::liveModuleHeaders($user);

        return Inertia::render('WorkspaceConfig/Hub', [
            'workspaces' => $workspaces,
            'summary' => [
                'total' => count($workspaces),
                'configured' => $configured,
                'active' => $active,
                'draft' => $draft,
                'missing' => $missing,
                'archived' => $archived,
                'with_criteria' => $withCriteria,
                'ready' => $ready,
                'partial' => $partial,
                'criteria_total' => EvaluationCriterion::query()->count(),
                'criteria_general' => $generalCriteria,
            ],
            'insights' => $this->insights->build($workspaces, $canManage),
            'coverage' => $this->insights->coverage($workspaces, $moduleHeaders),
            'filters' => [
                'include_archived' => $includeArchived,
            ],
            'viewer' => [
                'is_super_admin' => $user->isSuperAdmin(),
                'can_manage' => $canManage,
                'own_department_code' => $ownCode,
            ],
            'statusOptions' => array_merge(
                WorkspaceProfileStatus::options(),
                [['value' => 'missing', 'label' => 'Chưa kích hoạt']],
            ),
            'readinessOptions' => [
                ['value' => 'ready', 'label' => 'Đã sẵn sàng'],
                ['value' => 'partial', 'label' => 'Đang cấu hình'],
                ['value' => 'empty', 'label' => 'Chưa có nội dung'],
            ],
        ]);
    }
}
