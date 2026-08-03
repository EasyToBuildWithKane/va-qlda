<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\StoreEvaluationFormRequest;
use App\Http\Requests\Evaluation\StoreEvaluationFormTypeRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationFormRequest;
use App\Http\Resources\Evaluation\EvaluationFormResource;
use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormField;
use App\Models\Evaluation\EvaluationFormType;
use App\Models\Evaluation\EvaluationTemplate;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormRaterRole;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Evaluation\HrmEmployeeDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationFormController extends Controller
{
    public function __construct(
        private readonly HrmEmployeeDirectory $employees,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationForm::class);

        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'type_id' => trim((string) $request->query('type_id', '')),
            'template_id' => trim((string) $request->query('template_id', '')),
        ];

        $query = EvaluationForm::query()
            ->with([
                'creator:id,display_name,employee_id',
                'creator.employee:id,avatar_path',
                'manager:id,full_name,code',
                'type:id,name',
                'template:id,name,template_code',
            ])
            ->withCount(['criteria', 'assignees'])
            ->orderByDesc('id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('form_code', 'like', "%{$q}%");
            });
        }

        if ($filters['status'] !== '' && in_array($filters['status'], EvaluationFormStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['type_id'] !== '' && ctype_digit($filters['type_id'])) {
            $query->where('type_id', (int) $filters['type_id']);
        }

        if ($filters['template_id'] !== '' && ctype_digit($filters['template_id'])) {
            $query->where('template_id', (int) $filters['template_id']);
        }

        $items = $query->get()
            ->map(fn (EvaluationForm $form) => (new EvaluationFormResource($form))->resolve())
            ->values()
            ->all();

        $summaryBase = EvaluationForm::query();

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'draft' => (clone $summaryBase)->where('status', EvaluationFormStatus::Draft)->count(),
            'active' => (clone $summaryBase)->where('status', EvaluationFormStatus::Active)->count(),
            'closed' => (clone $summaryBase)->where('status', EvaluationFormStatus::Closed)->count(),
            'with_assignees' => (clone $summaryBase)->whereHas('assignees')->count(),
        ];

        return Inertia::render('WorkspaceConfig/EvaluationForms/Index', [
            'forms' => [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ],
            'filters' => $filters,
            'summary' => $summary,
            'statusOptions' => EvaluationFormStatus::options(),
            'typeOptions' => $this->typeOptions(),
            'templateOptions' => $this->templateOptions(),
            'can' => [
                'manage' => $user->can('create', EvaluationForm::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', EvaluationForm::class);

        $templateId = $request->query('template_id');
        $prefillCriteria = [];
        $prefillTemplateId = null;

        if ($templateId !== null && $templateId !== '' && ctype_digit((string) $templateId)) {
            $template = EvaluationTemplate::query()
                ->with([
                    'templateCriteria.criterion:id,criteria_code,criteria_name,score_levels',
                    'customCriteria',
                ])
                ->find((int) $templateId);

            if ($template) {
                $prefillTemplateId = $template->id;
                $prefillCriteria = $this->criteriaFromTemplate($template);
            }
        }

        return Inertia::render('WorkspaceConfig/EvaluationForms/Create', [
            ...$this->formCatalogProps(),
            'nextCode' => EvaluationForm::suggestNextCode(),
            'prefill' => [
                'template_id' => $prefillTemplateId,
                'criteria' => $prefillCriteria,
            ],
        ]);
    }

    public function store(StoreEvaluationFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        [$header, $extras] = $this->splitPayload($validated);

        $header['created_by'] = $request->user()->id;
        $header['status'] = $header['status'] ?? EvaluationFormStatus::Draft->value;

        if (! filled($header['form_code'] ?? null)) {
            $header['form_code'] = EvaluationForm::suggestNextCode();
        }

        $form = DB::transaction(function () use ($header, $extras) {
            $form = EvaluationForm::query()->create($header);
            $this->syncAllRelations($form, $extras);

            return $form;
        });

        return redirect()
            ->route('workspace.evaluation-forms.edit', $form)
            ->with('success', 'Đã tạo phiếu đánh giá.');
    }

    public function edit(Request $request, EvaluationForm $evaluationForm): Response
    {
        $this->authorize('update', $evaluationForm);

        $evaluationForm->load([
            'creator:id,display_name,employee_id',
            'creator.employee:id,avatar_path',
            'manager:id,full_name,code',
            'type:id,name',
            'template:id,name,template_code',
            'watchers.employee:id,full_name,code',
            'raters',
            'fields',
            'criteria.criterion:id,criteria_code,criteria_name,score_levels',
            'assignees',
        ]);

        return Inertia::render('WorkspaceConfig/EvaluationForms/Edit', [
            ...$this->formCatalogProps(),
            'form' => (new EvaluationFormResource($evaluationForm))->resolve(),
            'nextCode' => $evaluationForm->form_code,
            'can' => [
                'manage' => $request->user()->can('update', $evaluationForm),
            ],
        ]);
    }

    public function update(
        UpdateEvaluationFormRequest $request,
        EvaluationForm $evaluationForm,
    ): RedirectResponse {
        $validated = $request->validated();
        [$header, $extras] = $this->splitPayload($validated);

        if (! filled($header['form_code'] ?? null)) {
            $header['form_code'] = $evaluationForm->form_code;
        }

        DB::transaction(function () use ($evaluationForm, $header, $extras) {
            $evaluationForm->update($header);
            $this->syncAllRelations($evaluationForm, $extras);
        });

        return redirect()
            ->route('workspace.evaluation-forms.edit', $evaluationForm)
            ->with('success', 'Đã cập nhật phiếu đánh giá.');
    }

    public function destroy(EvaluationForm $evaluationForm): RedirectResponse
    {
        $this->authorize('delete', $evaluationForm);

        $evaluationForm->delete();

        return redirect()
            ->route('workspace.evaluation-forms.index')
            ->with('success', 'Đã xoá phiếu đánh giá.');
    }

    public function storeType(StoreEvaluationFormTypeRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        $type = EvaluationFormType::query()->create([
            'name' => $name,
            'sort_order' => ((int) EvaluationFormType::query()->max('sort_order')) + 1,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with([
            'success' => 'Đã thêm loại đánh giá.',
            'created_form_type' => [
                'id' => $type->id,
                'name' => $type->name,
            ],
        ]);
    }

    /**
     * JSON helper: criteria lines from a template (for FE hydrate when picking mẫu).
     */
    public function templateCriteria(EvaluationTemplate $evaluationTemplate): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', EvaluationForm::class);

        $evaluationTemplate->load([
            'templateCriteria.criterion:id,criteria_code,criteria_name,score_levels',
            'customCriteria',
        ]);

        return response()->json([
            'criteria' => $this->criteriaFromTemplate($evaluationTemplate),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitPayload(array $validated): array
    {
        $extras = [
            'watcher_ids' => $validated['watcher_ids'] ?? [],
            'raters' => $validated['raters'] ?? [],
            'fields' => $validated['fields'] ?? [],
            'criteria' => $validated['criteria'] ?? [],
            'assignees' => $validated['assignees'] ?? [],
        ];

        unset(
            $validated['watcher_ids'],
            $validated['raters'],
            $validated['fields'],
            $validated['criteria'],
            $validated['assignees'],
        );

        return [$validated, $extras];
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function syncAllRelations(EvaluationForm $form, array $extras): void
    {
        $form->watchers()->delete();
        foreach ($extras['watcher_ids'] ?? [] as $employeeId) {
            $form->watchers()->create(['employee_id' => (int) $employeeId]);
        }

        $form->raters()->delete();
        foreach (array_values($extras['raters'] ?? []) as $index => $rater) {
            $form->raters()->create([
                'role_key' => (string) $rater['role_key'],
                'label' => (string) $rater['label'],
                'weight_percent' => (float) ($rater['weight_percent'] ?? 0),
                'sort_order' => (int) ($rater['sort_order'] ?? $index),
            ]);
        }

        $form->fields()->delete();
        $fields = $extras['fields'] ?? [];
        if ($fields === []) {
            $fields = EvaluationFormField::DEFAULT_FIELDS;
        }
        foreach (array_values($fields) as $index => $field) {
            $form->fields()->create([
                'field_key' => (string) $field['field_key'],
                'label' => (string) $field['label'],
                'field_type' => (string) ($field['field_type'] ?? 'textarea'),
                'is_enabled' => (bool) ($field['is_enabled'] ?? true),
                'sort_order' => (int) ($field['sort_order'] ?? $index),
            ]);
        }

        $form->criteria()->delete();
        foreach (array_values($extras['criteria'] ?? []) as $index => $line) {
            $form->criteria()->create([
                'criterion_id' => $line['criterion_id'] ?? null,
                'name' => (string) $line['name'],
                'weight' => (float) ($line['weight'] ?? 0),
                'required_score_label' => $line['required_score_label'] ?? null,
                'evaluator_mode' => (string) ($line['evaluator_mode'] ?? 'all'),
                'evaluator_role_keys' => $line['evaluator_role_keys'] ?? [],
                'sort_order' => (int) ($line['sort_order'] ?? $index),
            ]);
        }

        $form->assignees()->delete();
        foreach (array_values($extras['assignees'] ?? []) as $index => $row) {
            $employee = Employee::query()->find((int) $row['employee_id']);
            $meta = is_array($employee?->meta) ? $employee->meta : [];

            $form->assignees()->create([
                'employee_id' => (int) $row['employee_id'],
                'employee_code' => $row['employee_code'] ?? $employee?->code,
                'employee_name' => $row['employee_name'] ?? $employee?->full_name,
                'department_code' => $row['department_code']
                    ?? ($meta['department_code'] ?? null),
                'department_name' => $row['department_name']
                    ?? ($meta['department_name'] ?? null),
                'dept_head_employee_id' => (int) $row['dept_head_employee_id'],
                'direct_manager_employee_id' => (int) $row['direct_manager_employee_id'],
                'board_employee_id' => $row['board_employee_id'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formCatalogProps(): array
    {
        return [
            'typeOptions' => $this->typeOptions(),
            'templateOptions' => $this->templateOptions(),
            'criteriaOptions' => $this->criteriaOptions(),
            'employeeOptions' => $this->employees->options(),
            'periodKindOptions' => EvaluationFormPeriodKind::options(),
            'orderOptions' => EvaluationFormOrder::options(),
            'statusOptions' => EvaluationFormStatus::options(),
            'defaultRaters' => EvaluationFormRaterRole::defaultRaters(),
            'defaultFields' => EvaluationFormField::DEFAULT_FIELDS,
            'raterRoleOptions' => EvaluationFormRaterRole::options(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function typeOptions(): array
    {
        return EvaluationFormType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (EvaluationFormType $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, template_code: string, label: string}>
     */
    private function templateOptions(): array
    {
        return EvaluationTemplate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'template_code'])
            ->map(fn (EvaluationTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'template_code' => $t->template_code,
                'label' => $t->name.' ('.$t->template_code.')',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function criteriaOptions(): array
    {
        return EvaluationCriterion::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'criteria_code', 'criteria_name', 'category', 'scope', 'department_name', 'score_levels'])
            ->map(fn (EvaluationCriterion $c) => [
                'id' => $c->id,
                'criteria_code' => $c->criteria_code,
                'criteria_name' => $c->criteria_name,
                'category' => $c->category,
                'scope' => $c->scope instanceof \BackedEnum ? $c->scope->value : (string) $c->scope,
                'department_name' => $c->department_name,
                'score_levels' => $c->normalizedScoreLevels(),
                'label' => $c->criteria_name.' ('.$c->criteria_code.')',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function criteriaFromTemplate(EvaluationTemplate $template): array
    {
        $lines = [];

        foreach ($template->templateCriteria as $index => $line) {
            $criterion = $line->criterion;
            $scoreLevels = $criterion?->normalizedScoreLevels() ?? [];
            $defaultLabel = $line->required_score_label;
            if (! $defaultLabel && $scoreLevels !== []) {
                $match = collect($scoreLevels)->first(
                    fn ($level) => stripos((string) ($level['label'] ?? ''), 'đạt') !== false
                );
                $defaultLabel = $match['label'] ?? ($scoreLevels[0]['label'] ?? null);
            }

            $lines[] = [
                'criterion_id' => $line->criterion_id,
                'name' => $criterion?->criteria_name ?? 'Tiêu chí',
                'weight' => (float) $line->weight,
                'required_score_label' => $defaultLabel,
                'evaluator_mode' => 'all',
                'evaluator_role_keys' => [],
                'sort_order' => $line->sort_order ?? $index,
                'score_levels' => $scoreLevels,
                'criteria_code' => $criterion?->criteria_code,
            ];
        }

        foreach ($template->customCriteria as $index => $line) {
            $lines[] = [
                'criterion_id' => null,
                'name' => $line->custom_name,
                'weight' => (float) $line->weight,
                'required_score_label' => $line->required_score_label,
                'evaluator_mode' => 'all',
                'evaluator_role_keys' => [],
                'sort_order' => 1000 + ($line->sort_order ?? $index),
                'score_levels' => [],
                'criteria_code' => $line->custom_code,
            ];
        }

        return collect($lines)
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}
