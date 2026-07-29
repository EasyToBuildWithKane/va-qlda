<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\ReorderEvaluationCriteriaRequest;
use App\Http\Requests\Evaluation\StoreEvaluationConfigRequest;
use App\Http\Requests\Evaluation\StoreEvaluationCriterionRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationConfigRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationCriterionRequest;
use App\Http\Resources\Evaluation\EvaluationConfigResource;
use App\Models\Evaluation\EvaluationConfig;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationTemplate;
use App\Support\Enums\EvaluationTemplateType;
use App\Support\Evaluation\EvaluationConfigFactory;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationConfigController extends Controller
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
        private readonly EvaluationConfigFactory $factory,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationConfig::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'department_code' => trim((string) $request->query('department_code', '')),
            'template_type' => trim((string) $request->query('template_type', '')),
            'status' => trim((string) $request->query('status', '')),
            'per_page' => min(50, max(10, (int) $request->query('per_page', 20))),
        ];

        $query = EvaluationConfig::query()
            ->withCount('criteria')
            ->with(['creator:id,display_name'])
            ->latest('id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('config_name', 'like', "%{$q}%")
                    ->orWhere('department_name', 'like', "%{$q}%")
                    ->orWhere('department_code', 'like', "%{$q}%");
            });
        }

        if ($filters['department_code'] !== '') {
            $query->where('department_code', $filters['department_code']);
        }

        if ($filters['template_type'] !== '' && in_array($filters['template_type'], EvaluationTemplateType::values(), true)) {
            $query->where('template_type', $filters['template_type']);
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        } elseif ($filters['status'] === 'effective') {
            $query->currentlyEffective();
        }

        $paginator = $query->paginate($filters['per_page'])->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (EvaluationConfig $c) => (new EvaluationConfigResource($c))->resolve())
            ->values()
            ->all();

        $today = now()->toDateString();
        $summary = [
            'total' => EvaluationConfig::query()->count(),
            'active' => EvaluationConfig::query()->where('is_active', true)->count(),
            'effective' => EvaluationConfig::query()->currentlyEffective($today)->count(),
            'point_system' => EvaluationConfig::query()->where('template_type', EvaluationTemplateType::PointSystem)->count(),
            'scorecard' => EvaluationConfig::query()->where('template_type', EvaluationTemplateType::Scorecard)->count(),
        ];

        $templates = EvaluationTemplate::query()
            ->withCount('criteria')
            ->orderBy('id')
            ->get()
            ->map(fn (EvaluationTemplate $t) => EvaluationConfigResource::templatePayload($t))
            ->values()
            ->all();

        return Inertia::render('WorkspaceConfig/Evaluation/Index', [
            'configs' => [
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
            'templates' => $templates,
            'templateTypeOptions' => EvaluationTemplateType::options(),
            'can' => [
                'manage' => $request->user()->can('create', EvaluationConfig::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', EvaluationConfig::class);

        return Inertia::render('WorkspaceConfig/Evaluation/Create', $this->formPayload($request));
    }

    public function store(StoreEvaluationConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = EvaluationTemplateType::from($data['template_type']);
        $applyTemplate = (bool) ($data['apply_template'] ?? false);
        $criteriaInput = $data['criteria'] ?? null;
        unset($data['apply_template'], $data['criteria']);

        if ($type === EvaluationTemplateType::PointSystem) {
            $data['base_score'] = $data['base_score'] ?? 100;
        } else {
            $data['base_score'] = null;
        }

        $data['is_active'] = $data['is_active'] ?? true;
        $data['created_by'] = $request->user()->id;

        $dept = $this->departments->findByCode($data['department_code']);
        if ($dept !== null) {
            $data['department_name'] = $dept['name'];
            $data['local_department_id'] = $data['local_department_id'] ?? $dept['local_department_id'];
        }

        $config = DB::transaction(function () use ($data, $applyTemplate, $criteriaInput, $type) {
            /** @var EvaluationConfig $config */
            $config = EvaluationConfig::query()->create($data);

            if ($applyTemplate && ! empty($data['template_id'])) {
                $template = EvaluationTemplate::query()->findOrFail($data['template_id']);
                $this->factory->copyFromTemplate($config, $template);
            } elseif (is_array($criteriaInput)) {
                $this->syncCriteria($config, $criteriaInput, $type);
            }

            return $config->fresh(['criteria']);
        });

        SecurityAuditLogger::evaluation(
            $request->user(),
            'config_created',
            $config->id,
            ['name' => $config->config_name, 'department_code' => $config->department_code]
        );

        return redirect()
            ->route('workspace.evaluation.show', $config)
            ->with('success', 'Đã tạo cấu hình đánh giá.');
    }

    public function show(Request $request, EvaluationConfig $evaluationConfig): Response
    {
        $this->authorize('view', $evaluationConfig);

        $evaluationConfig->load(['criteria', 'template', 'creator:id,display_name']);

        return Inertia::render('WorkspaceConfig/Evaluation/Show', [
            'config' => (new EvaluationConfigResource($evaluationConfig))->resolve(),
            'can' => [
                'manage' => $request->user()->can('update', $evaluationConfig),
            ],
        ]);
    }

    public function edit(Request $request, EvaluationConfig $evaluationConfig): Response
    {
        $this->authorize('update', $evaluationConfig);

        $evaluationConfig->load(['criteria', 'template']);

        return Inertia::render('WorkspaceConfig/Evaluation/Edit', [
            ...$this->formPayload($request),
            'config' => (new EvaluationConfigResource($evaluationConfig))->resolve(),
        ]);
    }

    public function update(UpdateEvaluationConfigRequest $request, EvaluationConfig $evaluationConfig): RedirectResponse
    {
        $data = $request->validated();
        $type = EvaluationTemplateType::from($data['template_type']);
        $criteriaInput = $data['criteria'] ?? null;
        unset($data['criteria']);

        if ($type === EvaluationTemplateType::PointSystem) {
            $data['base_score'] = $data['base_score'] ?? $evaluationConfig->base_score ?? 100;
        } else {
            $data['base_score'] = null;
        }

        $dept = $this->departments->findByCode($data['department_code']);
        if ($dept !== null) {
            $data['department_name'] = $dept['name'];
            $data['local_department_id'] = $data['local_department_id'] ?? $dept['local_department_id'];
        }

        DB::transaction(function () use ($evaluationConfig, $data, $criteriaInput, $type) {
            $evaluationConfig->update($data);
            if (is_array($criteriaInput)) {
                $this->syncCriteria($evaluationConfig, $criteriaInput, $type);
            }
        });

        SecurityAuditLogger::evaluation(
            $request->user(),
            'config_updated',
            $evaluationConfig->id,
            ['name' => $evaluationConfig->config_name]
        );

        return redirect()
            ->route('workspace.evaluation.show', $evaluationConfig)
            ->with('success', 'Đã cập nhật cấu hình đánh giá.');
    }

    public function destroy(Request $request, EvaluationConfig $evaluationConfig): RedirectResponse
    {
        $this->authorize('delete', $evaluationConfig);

        $name = $evaluationConfig->config_name;
        $id = $evaluationConfig->id;
        $evaluationConfig->delete();

        SecurityAuditLogger::evaluation(
            $request->user(),
            'config_deleted',
            $id,
            ['name' => $name]
        );

        return redirect()
            ->route('workspace.evaluation.index')
            ->with('success', 'Đã xóa cấu hình đánh giá.');
    }

    public function applyTemplate(Request $request, EvaluationConfig $evaluationConfig): RedirectResponse
    {
        $this->authorize('update', $evaluationConfig);

        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:evaluation_templates,id'],
        ], [
            'template_id.required' => 'Vui lòng chọn mẫu phiếu.',
        ]);

        $template = EvaluationTemplate::query()->findOrFail($data['template_id']);
        $this->factory->copyFromTemplate($evaluationConfig, $template);

        if ($template->template_type === EvaluationTemplateType::PointSystem && $evaluationConfig->base_score === null) {
            $evaluationConfig->update(['base_score' => 100]);
        }

        SecurityAuditLogger::evaluation(
            $request->user(),
            'template_applied',
            $evaluationConfig->id,
            ['template_id' => $template->id, 'template_name' => $template->name]
        );

        return back()->with('success', 'Đã áp dụng mẫu phiếu vào cấu hình.');
    }

    public function storeCriterion(StoreEvaluationCriterionRequest $request, EvaluationConfig $evaluationConfig): RedirectResponse
    {
        $data = $request->validated();
        $maxSort = (int) $evaluationConfig->criteria()->max('sort_order');
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);
        $data['is_active'] = $data['is_active'] ?? true;

        $criterion = $evaluationConfig->criteria()->create($data);

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_created',
            $evaluationConfig->id,
            ['criteria_code' => $criterion->criteria_code]
        );

        return back()->with('success', 'Đã thêm tiêu chí.');
    }

    public function updateCriterion(
        UpdateEvaluationCriterionRequest $request,
        EvaluationConfig $evaluationConfig,
        EvaluationCriterion $criterion,
    ): RedirectResponse {
        abort_unless($criterion->config_id === $evaluationConfig->id, 404);

        $criterion->update($request->validated());

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_updated',
            $evaluationConfig->id,
            ['criteria_code' => $criterion->criteria_code]
        );

        return back()->with('success', 'Đã cập nhật tiêu chí.');
    }

    public function destroyCriterion(
        Request $request,
        EvaluationConfig $evaluationConfig,
        EvaluationCriterion $criterion,
    ): RedirectResponse {
        $this->authorize('update', $evaluationConfig);
        abort_unless($criterion->config_id === $evaluationConfig->id, 404);

        $code = $criterion->criteria_code;
        $criterion->delete();

        SecurityAuditLogger::evaluation(
            $request->user(),
            'criteria_deleted',
            $evaluationConfig->id,
            ['criteria_code' => $code]
        );

        return back()->with('success', 'Đã xóa tiêu chí.');
    }

    public function reorderCriteria(
        ReorderEvaluationCriteriaRequest $request,
        EvaluationConfig $evaluationConfig,
    ): RedirectResponse {
        $ids = $request->validated('ordered_ids');
        $owned = $evaluationConfig->criteria()->whereIn('id', $ids)->pluck('id')->all();
        abort_unless(count($owned) === count($ids), 422, 'Danh sách tiêu chí không hợp lệ.');

        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                EvaluationCriterion::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return back()->with('success', 'Đã cập nhật thứ tự tiêu chí.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Request $request): array
    {
        $templates = EvaluationTemplate::query()
            ->with('criteria')
            ->withCount('criteria')
            ->orderBy('id')
            ->get()
            ->map(fn (EvaluationTemplate $t) => EvaluationConfigResource::templatePayload($t, true))
            ->values()
            ->all();

        return [
            'departments' => $this->departments->all(),
            'templates' => $templates,
            'templateTypeOptions' => EvaluationTemplateType::options(),
            'can' => [
                'manage' => $request->user()->can('create', EvaluationConfig::class),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncCriteria(EvaluationConfig $config, array $rows, EvaluationTemplateType $type): void
    {
        $keepIds = [];

        foreach (array_values($rows) as $i => $row) {
            $payload = [
                'criteria_code' => $row['criteria_code'],
                'criteria_name' => $row['criteria_name'],
                'category' => $row['category'],
                'description' => $row['description'] ?? null,
                'point_value' => $type === EvaluationTemplateType::PointSystem ? ($row['point_value'] ?? null) : null,
                'max_points' => $type === EvaluationTemplateType::PointSystem ? ($row['max_points'] ?? null) : null,
                'max_frequency' => $type === EvaluationTemplateType::PointSystem ? ($row['max_frequency'] ?? null) : null,
                'weight' => $type === EvaluationTemplateType::Scorecard ? ($row['weight'] ?? null) : null,
                'required_score' => $type === EvaluationTemplateType::Scorecard ? ($row['required_score'] ?? null) : null,
                'importance' => $type === EvaluationTemplateType::Scorecard ? ($row['importance'] ?? null) : null,
                'sort_order' => $row['sort_order'] ?? ($i + 1),
                'is_active' => $row['is_active'] ?? true,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;
            if ($id) {
                $criterion = EvaluationCriterion::query()
                    ->where('config_id', $config->id)
                    ->whereKey($id)
                    ->first();
                if ($criterion) {
                    $criterion->update($payload);
                    $keepIds[] = $criterion->id;

                    continue;
                }
            }

            $created = $config->criteria()->create($payload);
            $keepIds[] = $created->id;
        }

        $config->criteria()->whereNotIn('id', $keepIds)->delete();
    }
}
