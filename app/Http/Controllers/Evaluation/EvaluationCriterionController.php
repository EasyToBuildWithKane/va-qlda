<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\ImportEvaluationCriterionRequest;
use App\Http\Requests\Evaluation\StoreEvaluationCriterionRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationCriterionRequest;
use App\Http\Resources\Evaluation\EvaluationCriterionResource;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SecurityAuditLog;
use App\Services\NotificationService;
use App\Support\Audit\AuditActionCatalog;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\NotificationType;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\PublicMediaUrl;
use App\Support\SecurityAuditLogger;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ];

        $query = EvaluationCriterion::query()
            ->with(['creator:id,display_name'])
            ->orderByRaw("CASE WHEN scope = 'general' THEN 0 ELSE 1 END")
            ->orderBy('department_name')
            ->orderByRaw("CASE WHEN category IS NULL OR category = '' THEN 1 ELSE 0 END")
            ->orderBy('category')
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

        $items = $query->get()
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
                    'total' => count($items),
                ],
            ],
            'filters' => $filters,
            'summary' => $summary,
            'departments' => $departmentOptions,
            'categories' => $this->categoryOptions($forcedDept),
            'scopeOptions' => EvaluationCriterionScope::options(),
            'nextCode' => EvaluationCriterion::suggestNextCode(),
            'defaultScoreLevels' => EvaluationCriterion::DEFAULT_SCORE_LEVELS,
            'viewer' => [
                'can_manage_all' => $canManageAll,
                'can_create_general' => $this->scope->canManageAll($user),
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
            $this->criterionAuditMeta($criterion)
        );

        return back()->with('success', 'Đã tạo tiêu chí đánh giá.');
    }

    public function import(ImportEvaluationCriterionRequest $request): RedirectResponse
    {
        $rows = $request->validated('rows');
        $account = $request->user();
        $created = 0;

        DB::transaction(function () use ($rows, $account, &$created) {
            foreach ($rows as $row) {
                $data = $this->normalizePayload($row);
                $data['created_by'] = $account->id;
                $data['is_active'] = $data['is_active'] ?? true;
                $data['allow_half_score'] = $data['allow_half_score'] ?? false;
                $data['sort_order'] = (int) EvaluationCriterion::query()->max('sort_order') + 1;

                if (! filled($data['criteria_code'] ?? null)) {
                    $data['criteria_code'] = EvaluationCriterion::suggestNextCode();
                }

                $criterion = EvaluationCriterion::query()->create($data);

                SecurityAuditLogger::evaluation(
                    $account,
                    'criteria_created',
                    $criterion->id,
                    $this->criterionAuditMeta($criterion)
                );

                $created++;
            }
        });

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            "Đã nhập {$created} tiêu chí đánh giá từ Excel",
            null,
            null,
        );

        return back()->with('success', "Đã nhập {$created} tiêu chí đánh giá từ file.");
    }

    public function show(Request $request, EvaluationCriterion $evaluationCriterion): Response
    {
        $this->authorize('view', $evaluationCriterion);

        $evaluationCriterion->load([
            'creator:id,display_name,employee_id',
            'creator.employee:id,avatar_path',
        ]);
        $user = $request->user();

        return Inertia::render('WorkspaceConfig/Evaluation/Show', [
            'criterion' => (new EvaluationCriterionResource($evaluationCriterion))->resolve(),
            'activity' => $this->activityFor($evaluationCriterion),
            'departments' => $this->departments->all(),
            'categories' => $this->categoryOptions(),
            'scopeOptions' => EvaluationCriterionScope::options(),
            'defaultScoreLevels' => EvaluationCriterion::DEFAULT_SCORE_LEVELS,
            'viewer' => [
                'can_create_general' => $this->scope->canManageAll($user),
                'own_department_code' => $this->scope->ownDepartmentCode($user),
            ],
            'can' => [
                'manage' => $user->can('update', $evaluationCriterion),
            ],
        ]);
    }

    public function update(
        UpdateEvaluationCriterionRequest $request,
        EvaluationCriterion $evaluationCriterion,
    ): RedirectResponse {
        $before = $this->criterionSnapshot($evaluationCriterion);
        $data = $this->normalizePayload($request->validated());
        $evaluationCriterion->update($data);
        $evaluationCriterion->refresh();

        $meta = $this->criterionAuditMeta($evaluationCriterion);
        $meta['changes'] = $this->diffCriterionSnapshot($before, $this->criterionSnapshot($evaluationCriterion));

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_updated',
            $evaluationCriterion->id,
            $meta
        );

        return back()->with('success', 'Đã cập nhật tiêu chí đánh giá.');
    }

    public function destroy(Request $request, EvaluationCriterion $evaluationCriterion): RedirectResponse
    {
        $this->authorize('delete', $evaluationCriterion);

        $id = $evaluationCriterion->id;
        $meta = $this->criterionAuditMeta($evaluationCriterion);
        $evaluationCriterion->delete();

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_deleted',
            $id,
            $meta
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

        $data['allow_half_score'] = (bool) ($data['allow_half_score'] ?? false);
        $data['score_levels'] = EvaluationCriterion::normalizeScoreLevels(
            $data['score_levels'] ?? null,
            $data['allow_half_score'],
        );

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
            ->with(['actor:id,display_name,employee_id', 'actor.employee:id,avatar_path'])
            ->where('subject_type', 'evaluation_criterion')
            ->where('subject_id', $criterion->id)
            ->where('action', 'like', 'evaluation.criteria_%')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (SecurityAuditLog $log) {
                $meta = AuditActionCatalog::describe($log->action);
                $payload = is_array($log->meta) ? $log->meta : [];

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'label' => $meta['label'],
                    'actor_name' => $log->actor?->display_name ?? 'Hệ thống',
                    'actor_avatar' => PublicMediaUrl::fromPublicDisk(
                        $log->actor?->employee?->avatar_path
                    ),
                    'created_at' => $log->created_at?->toIso8601String(),
                    'meta' => $payload,
                    'changes' => $payload['changes'] ?? [],
                    'score_summary' => $payload['score_summary'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function criterionAuditMeta(EvaluationCriterion $criterion): array
    {
        $levels = $criterion->normalizedScoreLevels();

        return [
            'criteria_code' => $criterion->criteria_code,
            'criteria_name' => $criterion->criteria_name,
            'category' => $criterion->category,
            'scope' => $criterion->scope instanceof EvaluationCriterionScope
                ? $criterion->scope->value
                : (string) $criterion->scope,
            'department_code' => $criterion->department_code,
            'allow_half_score' => (bool) $criterion->allow_half_score,
            'is_active' => (bool) $criterion->is_active,
            'score_levels_count' => count($levels),
            'score_summary' => $this->formatScoreSummary($levels),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function criterionSnapshot(EvaluationCriterion $criterion): array
    {
        return [
            'criteria_name' => $criterion->criteria_name,
            'category' => $criterion->category,
            'description' => $criterion->description,
            'scope' => $criterion->scope instanceof EvaluationCriterionScope
                ? $criterion->scope->value
                : (string) $criterion->scope,
            'department_code' => $criterion->department_code,
            'department_name' => $criterion->department_name,
            'allow_half_score' => (bool) $criterion->allow_half_score,
            'is_active' => (bool) $criterion->is_active,
            'score_summary' => $this->formatScoreSummary($criterion->normalizedScoreLevels()),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{label: string, from?: string, to?: string}>
     */
    private function diffCriterionSnapshot(array $before, array $after): array
    {
        $labels = [
            'criteria_name' => 'Tên tiêu chí',
            'category' => 'Loại tiêu chí',
            'description' => 'Mô tả',
            'scope' => 'Phạm vi',
            'department_name' => 'Phòng ban',
            'allow_half_score' => 'Chấm điểm 0.5',
            'is_active' => 'Trạng thái',
            'score_summary' => 'Thang điểm',
        ];

        $changes = [];
        foreach ($labels as $key => $label) {
            $from = $this->formatAuditValue($key, $before[$key] ?? null);
            $to = $this->formatAuditValue($key, $after[$key] ?? null);
            if ($from === $to) {
                continue;
            }
            $changes[] = [
                'label' => $label,
                'from' => $from,
                'to' => $to,
            ];
        }

        return $changes;
    }

    private function formatAuditValue(string $key, mixed $value): string
    {
        if ($key === 'allow_half_score') {
            return $value ? 'Có' : 'Không';
        }
        if ($key === 'is_active') {
            return $value ? 'Đang hoạt động' : 'Ngưng hoạt động';
        }
        if ($key === 'scope') {
            return $value === EvaluationCriterionScope::General->value
                ? EvaluationCriterionScope::General->label()
                : EvaluationCriterionScope::Department->label();
        }
        if ($value === null || $value === '') {
            return 'Chưa cập nhật';
        }

        return (string) $value;
    }

    /**
     * @param  list<array{code?: string, label?: string, weight?: int|float}>  $levels
     */
    private function formatScoreSummary(array $levels): string
    {
        if ($levels === []) {
            return '';
        }

        return collect($levels)
            ->map(function (array $level) {
                $code = trim((string) ($level['code'] ?? ''));
                $weight = $level['weight'] ?? null;
                $weightText = is_numeric($weight)
                    ? ((float) $weight > 0 ? '+'.$weight : (string) $weight)
                    : '';

                return trim($code.($weightText !== '' ? ' '.$weightText : ''));
            })
            ->filter()
            ->implode(' · ');
    }
}
