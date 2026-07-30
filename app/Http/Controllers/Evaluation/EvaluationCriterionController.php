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
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationCriterionController extends Controller
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationCriterion::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'scope' => trim((string) $request->query('scope', '')),
            'department_code' => trim((string) $request->query('department_code', '')),
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

        if ($filters['department_code'] !== '') {
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

        $summary = [
            'total' => EvaluationCriterion::query()->count(),
            'general' => EvaluationCriterion::query()->general()->count(),
            'department' => EvaluationCriterion::query()->forDepartment()->count(),
            'active' => EvaluationCriterion::query()->where('is_active', true)->count(),
            'inactive' => EvaluationCriterion::query()->where('is_active', false)->count(),
        ];

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
            'departments' => $this->departments->all(),
            'categories' => $this->categoryOptions(),
            'scopeOptions' => EvaluationCriterionScope::options(),
            'nextCode' => EvaluationCriterion::suggestNextCode(),
            'defaultScoreLabels' => EvaluationCriterion::DEFAULT_SCORE_LABELS,
            'can' => [
                'manage' => $request->user()->can('create', EvaluationCriterion::class),
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

        return $data;
    }

    /** @return list<string> */
    private function categoryOptions(): array
    {
        return EvaluationCriterion::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
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
