<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\StoreEvaluationCriterionRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationCriterionRequest;
use App\Http\Resources\Evaluation\EvaluationCriterionResource;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SecurityAuditLog;
use App\Support\Audit\AuditActionCatalog;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\EvaluationScoringType;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\SecurityAuditLogger;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationCriterionController extends Controller
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
        private readonly WorkspaceScopeResolver $scope,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationCriterion::class);

        $user = $request->user();
        $canManageAll = $this->scope->canManageAll($user)
            || $user->allows('workspace.evaluation.manage')
            || $user->allows('workspace.evaluation.view');
        $forcedDept = $canManageAll ? null : $this->scope->ownDepartmentCode($user);

        if (! $canManageAll) {
            abort_if($forcedDept === null, 403);
            $requestedDept = trim((string) $request->query('department_code', ''));
            if ($requestedDept !== '' && strcasecmp($requestedDept, $forcedDept) !== 0) {
                abort(403);
            }
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'department_code' => $forcedDept ?? trim((string) $request->query('department_code', '')),
            'category' => trim((string) $request->query('category', '')),
            'status' => trim((string) $request->query('status', '')),
            'per_page' => min(50, max(10, (int) $request->query('per_page', 20))),
        ];

        $query = EvaluationCriterion::query()
            ->with(['creator:id,display_name'])
            ->orderByRaw("CASE WHEN scope = 'general' THEN 0 ELSE 1 END")
            ->orderBy('department_name')
            ->orderBy('sort_order')
            ->orderBy('id');

        $this->applyViewerScope($query, $forcedDept);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('criteria_name', 'like', "%{$q}%")
                    ->orWhere('criteria_code', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('department_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($filters['scope'] !== '' && in_array($filters['scope'], EvaluationCriterionScope::values(), true)) {
            $query->where('scope', $filters['scope']);
        }

        if ($filters['department_code'] !== '' && $canManageAll) {
            $query->where('department_code', $filters['department_code']);
        }

        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $paginator = $query->paginate($filters['per_page'])->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (EvaluationCriterion $c) => (new EvaluationCriterionResource($c))->resolve())
            ->values()
            ->all();

        $summaryBase = EvaluationCriterion::query();
        $this->applyViewerScope($summaryBase, $forcedDept);

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'general' => (clone $summaryBase)->general()->count(),
            'department' => (clone $summaryBase)->forDepartment()->count(),
            'active' => (clone $summaryBase)->where('is_active', true)->count(),
            'inactive' => (clone $summaryBase)->where('is_active', false)->count(),
        ];

        $departmentOptions = $canManageAll
            ? $this->departments->all()
            : array_values(array_filter(
                $this->departments->all(),
                static fn (array $d): bool => strcasecmp($d['code'], (string) $forcedDept) === 0,
            ));

        return Inertia::render('WorkspaceConfig/Evaluation/Index', [
            'criteria' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'links' => $paginator->linkCollection()->toArray(),
                ],
            ],
            'filters' => $filters,
            'summary' => $summary,
            'departments' => $departmentOptions,
            'categories' => $this->categoryOptions($forcedDept),
            'scopeOptions' => EvaluationCriterionScope::options(),
            'scoringTypeOptions' => EvaluationScoringType::options(),
            'nextCode' => EvaluationCriterion::suggestNextCode(),
            'defaultScoreLabels' => EvaluationCriterion::DEFAULT_SCORE_LABELS,
            'viewer' => [
                'can_manage_all' => $canManageAll,
                'own_department_code' => $this->scope->ownDepartmentCode($user),
                'forced_department_code' => $forcedDept,
            ],
            'can' => [
                'manage' => $user->can('create', EvaluationCriterion::class),
            ],
        ]);
    }

    public function store(StoreEvaluationCriterionRequest $request): RedirectResponse
    {
        $data = $this->normalizePayload($request->validated());
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['allow_half_score'] = $data['allow_half_score'] ?? false;
        $data['sort_order'] = $data['sort_order'] ?? ((int) EvaluationCriterion::query()->max('sort_order') + 1);

        if (! filled($data['criteria_code'] ?? null)) {
            $data['criteria_code'] = EvaluationCriterion::suggestNextCode();
        }

        $criterion = EvaluationCriterion::query()->create($data);

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_created',
            $criterion->id,
            [
                'criteria_code' => $criterion->criteria_code,
                'criteria_name' => $criterion->criteria_name,
            ]
        );

        return redirect()
            ->route('workspace.evaluation.show', $criterion)
            ->with('success', 'Đã tạo tiêu chí đánh giá.');
    }

    public function show(Request $request, EvaluationCriterion $evaluationCriterion): Response
    {
        $this->authorize('view', $evaluationCriterion);

        $evaluationCriterion->load(['creator:id,display_name']);

        return Inertia::render('WorkspaceConfig/Evaluation/Show', [
            'criterion' => (new EvaluationCriterionResource($evaluationCriterion))->resolve(),
            'activity' => $this->activityFor($evaluationCriterion),
            'departments' => $this->departments->all(),
            'categories' => $this->categoryOptions(),
            'scopeOptions' => EvaluationCriterionScope::options(),
            'scoringTypeOptions' => EvaluationScoringType::options(),
            'defaultScoreLabels' => EvaluationCriterion::DEFAULT_SCORE_LABELS,
            'can' => [
                'manage' => $request->user()->can('update', $evaluationCriterion),
            ],
        ]);
    }

    public function update(
        UpdateEvaluationCriterionRequest $request,
        EvaluationCriterion $evaluationCriterion,
    ): RedirectResponse {
        $data = $this->normalizePayload($request->validated());
        $evaluationCriterion->update($data);

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_updated',
            $evaluationCriterion->id,
            [
                'criteria_code' => $evaluationCriterion->criteria_code,
                'criteria_name' => $evaluationCriterion->criteria_name,
            ]
        );

        return redirect()
            ->route('workspace.evaluation.show', $evaluationCriterion)
            ->with('success', 'Đã cập nhật tiêu chí đánh giá.');
    }

    public function destroy(Request $request, EvaluationCriterion $evaluationCriterion): RedirectResponse
    {
        $this->authorize('delete', $evaluationCriterion);

        $id = $evaluationCriterion->id;
        $code = $evaluationCriterion->criteria_code;
        $name = $evaluationCriterion->criteria_name;
        $evaluationCriterion->delete();

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_deleted',
            $id,
            ['criteria_code' => $code, 'criteria_name' => $name]
        );

        return redirect()
            ->route('workspace.evaluation.index')
            ->with('success', 'Đã xóa tiêu chí đánh giá.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $scope = EvaluationCriterionScope::from($data['scope']);
        $scoringType = EvaluationScoringType::from($data['scoring_type'] ?? EvaluationScoringType::Scale->value);
        $data['scoring_type'] = $scoringType->value;

        if ($scope === EvaluationCriterionScope::General) {
            $data['department_code'] = null;
            $data['department_name'] = null;
            $data['local_department_id'] = null;
        } else {
            $dept = $this->departments->findByCode((string) ($data['department_code'] ?? ''));
            if ($dept !== null) {
                $data['department_name'] = $dept['name'];
                $data['local_department_id'] = $data['local_department_id'] ?? $dept['local_department_id'];
            }
        }

        if ($scoringType === EvaluationScoringType::Points) {
            $data['allow_half_score'] = false;
            $data['point_bonus'] = (int) ($data['point_bonus'] ?? 0);
            $data['point_penalty'] = (int) ($data['point_penalty'] ?? 0);
            // NOT NULL columns — placeholder when not used for scale mode
            $defaults = EvaluationCriterion::DEFAULT_SCORE_LABELS;
            $data['score_1'] = $data['score_1'] ?: $defaults[1];
            $data['score_2'] = $data['score_2'] ?: $defaults[2];
            $data['score_3'] = $data['score_3'] ?: $defaults[3];
            $data['score_4'] = $data['score_4'] ?: $defaults[4];
            $data['score_5'] = $data['score_5'] ?: $defaults[5];
        } else {
            $data['point_bonus'] = null;
            $data['point_penalty'] = null;
            $data['allow_half_score'] = (bool) ($data['allow_half_score'] ?? false);
        }

        return $data;
    }

    /**
     * Non-managers see general criteria + their department only.
     */
    private function applyViewerScope(Builder $query, ?string $forcedDept): void
    {
        if ($forcedDept === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($forcedDept) {
            $builder->where('scope', EvaluationCriterionScope::General)
                ->orWhere(function (Builder $inner) use ($forcedDept) {
                    $inner->where('scope', EvaluationCriterionScope::Department)
                        ->where('department_code', $forcedDept);
                });
        });
    }

    /** @return list<string> */
    private function categoryOptions(?string $forcedDept = null): array
    {
        $query = EvaluationCriterion::query()
            ->whereNotNull('category')
            ->where('category', '!=', '');

        $this->applyViewerScope($query, $forcedDept);

        return $query
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityFor(EvaluationCriterion $criterion): array
    {
        return SecurityAuditLog::query()
            ->with('actor:id,display_name')
            ->where('subject_type', 'evaluation_criterion')
            ->where('subject_id', $criterion->id)
            ->where('action', 'like', 'evaluation.criteria_%')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(function (SecurityAuditLog $log) {
                $meta = AuditActionCatalog::describe($log->action);

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'label' => $meta['label'],
                    'actor_name' => $log->actor?->display_name ?? 'Hệ thống',
                    'created_at' => $log->created_at?->toIso8601String(),
                    'meta' => $log->meta,
                ];
            })
            ->values()
            ->all();
    }
}
