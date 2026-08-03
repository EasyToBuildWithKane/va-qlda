<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\ImportEvaluationTemplateRequest;
use App\Http\Requests\Evaluation\RecordEvaluationTemplateExportRequest;
use App\Http\Requests\Evaluation\StoreEvaluationTemplateRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationTemplateRequest;
use App\Http\Resources\Evaluation\EvaluationFormResource;
use App\Http\Resources\Evaluation\EvaluationTemplateResource;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateExportLog;
use App\Models\SecurityAuditLog;
use App\Services\NotificationService;
use App\Support\Audit\AuditActionCatalog;
use App\Support\Enums\EvaluationTemplateFieldType;
use App\Support\Enums\EvaluationTemplateTargetKind;
use App\Support\Enums\NotificationType;
use App\Support\Evaluation\HrmJobCatalogDirectory;
use App\Support\Evaluation\HrmPositionDirectory;
use App\Support\PublicMediaUrl;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationTemplateController extends Controller
{
    public function __construct(
        private readonly HrmPositionDirectory $positions,
        private readonly HrmJobCatalogDirectory $jobCatalog,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EvaluationTemplate::class);

        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'position_code' => trim((string) $request->query('position_code', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        $query = EvaluationTemplate::query()
            ->with([
                'creator:id,display_name',
                'templateCriteria.criterion:id,criteria_code,criteria_name,category,is_active',
                'customCriteria',
                'targets',
                'fields',
            ])
            ->withCount(['templateCriteria', 'customCriteria'])
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('template_code', 'like', "%{$q}%")
                    ->orWhere('position_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($filters['position_code'] !== '') {
            $query->where(function ($builder) use ($filters) {
                $builder->where('position_code', $filters['position_code'])
                    ->orWhereHas('targets', function ($t) use ($filters) {
                        $t->where('code', $filters['position_code']);
                    });
            });
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->get()
            ->map(fn (EvaluationTemplate $t) => (new EvaluationTemplateResource($t))->resolve())
            ->values()
            ->all();

        $summaryBase = EvaluationTemplate::query();

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'active' => (clone $summaryBase)->where('is_active', true)->count(),
            'inactive' => (clone $summaryBase)->where('is_active', false)->count(),
            'with_position' => (clone $summaryBase)->whereNotNull('position_code')->where('position_code', '!=', '')->count(),
            'with_criteria' => (clone $summaryBase)->whereHas('templateCriteria')->count(),
        ];

        return Inertia::render('WorkspaceConfig/EvaluationTemplates/Index', [
            'templates' => [
                'data' => $items,
                'meta' => ['total' => count($items)],
            ],
            'filters' => $filters,
            'summary' => $summary,
            'positions' => $this->positions->all(),
            ...$this->formCatalogProps(),
            'nextCode' => EvaluationTemplate::suggestNextCode(),
            'exportLogs' => $this->exportLogsPayload(),
            'can' => [
                'manage' => $user->can('create', EvaluationTemplate::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', EvaluationTemplate::class);

        return Inertia::render('WorkspaceConfig/EvaluationTemplates/Create', [
            ...$this->formCatalogProps(),
            'nextCode' => EvaluationTemplate::suggestNextCode(),
        ]);
    }

    public function store(StoreEvaluationTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        [$header, $extras] = $this->splitPayload($validated);

        $header['created_by'] = $request->user()->id;
        $header['is_active'] = $header['is_active'] ?? true;
        $header['sort_order'] = $header['sort_order'] ?? ((int) EvaluationTemplate::query()->max('sort_order') + 1);

        if (! filled($header['template_code'] ?? null)) {
            $header['template_code'] = EvaluationTemplate::suggestNextCode();
        }

        $template = DB::transaction(function () use ($header, $extras) {
            $template = EvaluationTemplate::query()->create($header);
            $this->syncAllRelations($template, $extras);

            return $template;
        });

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_created',
            $template->id,
            $this->templateAuditMeta($template->fresh(['templateCriteria', 'customCriteria', 'targets', 'fields']))
        );

        return redirect()
            ->route('workspace.evaluation-templates.show', $template)
            ->with('success', 'Đã tạo mẫu đánh giá.');
    }

    public function import(ImportEvaluationTemplateRequest $request): RedirectResponse
    {
        $rows = $request->validated('rows');
        $account = $request->user();
        $created = 0;

        DB::transaction(function () use ($rows, $account, &$created) {
            foreach ($rows as $row) {
                [$header, $extras] = $this->splitPayload($row);

                $header['created_by'] = $account->id;
                $header['is_active'] = $header['is_active'] ?? true;
                $header['sort_order'] = (int) EvaluationTemplate::query()->max('sort_order') + 1;

                if (! filled($header['template_code'] ?? null)) {
                    $header['template_code'] = EvaluationTemplate::suggestNextCode();
                }

                $template = EvaluationTemplate::query()->create($header);
                $this->syncAllRelations($template, $extras);

                SecurityAuditLogger::evaluationTemplate(
                    $account,
                    'template_created',
                    $template->id,
                    $this->templateAuditMeta($template->fresh(['templateCriteria', 'customCriteria', 'targets', 'fields']))
                );

                $created++;
            }
        });

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            "Đã nhập {$created} mẫu đánh giá từ Excel",
            null,
            null,
        );

        return back()->with('success', "Đã nhập {$created} mẫu đánh giá từ file.");
    }

    public function show(Request $request, EvaluationTemplate $evaluationTemplate): Response
    {
        $this->authorize('view', $evaluationTemplate);

        $evaluationTemplate->load([
            'creator:id,display_name,employee_id',
            'creator.employee:id,avatar_path',
            'templateCriteria.criterion:id,criteria_code,criteria_name,category,is_active',
            'customCriteria',
            'targets',
            'fields',
        ]);

        $user = $request->user();

        $relatedForms = EvaluationForm::query()
            ->where('template_id', $evaluationTemplate->id)
            ->with([
                'creator:id,display_name,employee_id',
                'creator.employee:id,avatar_path',
                'type:id,name',
            ])
            ->withCount(['criteria', 'assignees'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (EvaluationForm $form) => (new EvaluationFormResource($form))->resolve())
            ->values()
            ->all();

        return Inertia::render('WorkspaceConfig/EvaluationTemplates/Show', [
            'template' => (new EvaluationTemplateResource($evaluationTemplate))->resolve(),
            'activity' => $this->activityFor($evaluationTemplate),
            'positions' => $this->positions->all(),
            ...$this->formCatalogProps(),
            'relatedForms' => $relatedForms,
            'can' => [
                'manage' => $user->can('update', $evaluationTemplate),
                'manage_forms' => $user->can('create', EvaluationForm::class),
            ],
        ]);
    }

    public function update(
        UpdateEvaluationTemplateRequest $request,
        EvaluationTemplate $evaluationTemplate,
    ): RedirectResponse {
        $before = $this->templateSnapshot($evaluationTemplate);
        [$header, $extras] = $this->splitPayload($request->validated());

        if (! filled($header['template_code'] ?? null)) {
            $header['template_code'] = $evaluationTemplate->template_code;
        }

        DB::transaction(function () use ($evaluationTemplate, $header, $extras) {
            $evaluationTemplate->update($header);
            $this->syncAllRelations($evaluationTemplate, $extras);
        });

        $evaluationTemplate->refresh()->load(['templateCriteria', 'customCriteria', 'targets', 'fields']);

        $meta = $this->templateAuditMeta($evaluationTemplate);
        $meta['changes'] = $this->diffTemplateSnapshot($before, $this->templateSnapshot($evaluationTemplate));

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_updated',
            $evaluationTemplate->id,
            $meta
        );

        return back()->with('success', 'Đã cập nhật mẫu đánh giá.');
    }

    public function destroy(Request $request, EvaluationTemplate $evaluationTemplate): RedirectResponse
    {
        $this->authorize('delete', $evaluationTemplate);

        $id = $evaluationTemplate->id;
        $meta = $this->templateAuditMeta($evaluationTemplate->load('templateCriteria'));
        $evaluationTemplate->delete();

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_deleted',
            $id,
            $meta
        );

        return redirect()
            ->route('workspace.evaluation-templates.index')
            ->with('success', 'Đã xóa mẫu đánh giá.');
    }

    public function duplicate(Request $request, EvaluationTemplate $evaluationTemplate): RedirectResponse
    {
        $this->authorize('create', EvaluationTemplate::class);

        $evaluationTemplate->load(['templateCriteria', 'customCriteria', 'targets', 'fields']);

        $copy = DB::transaction(function () use ($request, $evaluationTemplate) {
            $copy = EvaluationTemplate::query()->create([
                'template_code' => EvaluationTemplate::suggestNextCode(),
                'name' => $evaluationTemplate->name.' (bản sao)',
                'description' => $evaluationTemplate->description,
                'position_code' => $evaluationTemplate->position_code,
                'position_name' => $evaluationTemplate->position_name,
                'sort_order' => (int) EvaluationTemplate::query()->max('sort_order') + 1,
                'is_active' => $evaluationTemplate->is_active,
                'created_by' => $request->user()->id,
            ]);

            $catalog = $evaluationTemplate->templateCriteria->map(fn ($line) => [
                'source' => 'catalog',
                'criterion_id' => $line->criterion_id,
                'weight' => $line->weight,
                'required_score_label' => $line->required_score_label,
                'include_in_total' => $line->include_in_total,
                'sort_order' => $line->sort_order,
            ])->all();

            $custom = $evaluationTemplate->customCriteria->map(fn ($line) => [
                'source' => 'custom',
                'custom_name' => $line->custom_name,
                'custom_code' => $line->custom_code,
                'custom_category' => $line->custom_category,
                'custom_description' => $line->custom_description,
                'weight' => $line->weight,
                'required_score_label' => $line->required_score_label,
                'include_in_total' => $line->include_in_total,
                'sort_order' => $line->sort_order,
            ])->all();

            $this->syncAllRelations($copy, [
                'titles' => $evaluationTemplate->targets
                    ->where('kind', EvaluationTemplateTargetKind::Title->value)
                    ->map(fn ($t) => [
                        'code' => $t->code,
                        'name' => $t->name,
                        'hrm_uuid' => $t->hrm_uuid,
                        'source' => $t->source,
                    ])->values()->all(),
                'ranks' => $evaluationTemplate->targets
                    ->where('kind', EvaluationTemplateTargetKind::Rank->value)
                    ->map(fn ($t) => [
                        'code' => $t->code,
                        'name' => $t->name,
                        'hrm_uuid' => $t->hrm_uuid,
                        'source' => $t->source,
                    ])->values()->all(),
                'criteria' => array_merge($catalog, $custom),
                'fields' => $evaluationTemplate->fields->map(fn ($f) => [
                    'field_key' => $f->field_key,
                    'label' => $f->label,
                    'field_type' => $f->field_type instanceof EvaluationTemplateFieldType
                        ? $f->field_type->value
                        : (string) $f->field_type,
                    'options' => $f->options ?? [],
                    'is_required' => $f->is_required,
                    'placeholder' => $f->placeholder,
                    'help_text' => $f->help_text,
                    'sort_order' => $f->sort_order,
                ])->all(),
            ]);

            return $copy;
        });

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_duplicated',
            $copy->id,
            array_merge($this->templateAuditMeta($copy->fresh(['templateCriteria', 'customCriteria', 'targets', 'fields'])), [
                'source_template_id' => $evaluationTemplate->id,
                'source_template_code' => $evaluationTemplate->template_code,
            ])
        );

        return redirect()
            ->route('workspace.evaluation-templates.show', $copy)
            ->with('success', 'Đã nhân bản mẫu đánh giá.');
    }

    public function exportLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EvaluationTemplate::class);

        return response()->json([
            'data' => $this->exportLogsPayload(),
        ]);
    }

    public function recordExport(RecordEvaluationTemplateExportRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $account = $request->user();

        $log = EvaluationTemplateExportLog::query()->create([
            'exported_by' => $account->id,
            'scope' => $data['scope'],
            'format' => $data['format'],
            'row_count' => (int) $data['row_count'],
            'columns' => $data['columns'] ?? [],
            'filters' => $data['filters'] ?? null,
            'filename' => $data['filename'] ?? null,
        ]);

        SecurityAuditLogger::evaluationTemplate(
            $account,
            'template_exported',
            null,
            [
                'export_log_id' => $log->id,
                'scope' => $log->scope,
                'format' => $log->format,
                'row_count' => $log->row_count,
                'filename' => $log->filename,
                'columns' => $log->columns,
            ]
        );

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'log' => $this->formatExportLog($log->load('exporter:id,display_name')),
            ]);
        }

        return back()->with('success', 'Đã ghi lịch sử xuất mẫu đánh giá.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array{titles: list<array<string, mixed>>, ranks: list<array<string, mixed>>, criteria: list<array<string, mixed>>, fields: list<array<string, mixed>>}}
     */
    private function splitPayload(array $data): array
    {
        $titles = is_array($data['titles'] ?? null) ? $data['titles'] : [];
        $ranks = is_array($data['ranks'] ?? null) ? $data['ranks'] : [];
        $criteria = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        unset($data['titles'], $data['ranks'], $data['criteria'], $data['fields']);

        $data = $this->normalizeHeaderFromTargets($data, $titles, $ranks);

        return [$data, [
            'titles' => $titles,
            'ranks' => $ranks,
            'criteria' => $criteria,
            'fields' => $fields,
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $titles
     * @param  list<array<string, mixed>>  $ranks
     * @return array<string, mixed>
     */
    private function normalizeHeaderFromTargets(array $data, array $titles, array $ranks): array
    {
        if ($titles !== []) {
            $first = $titles[0];
            $data['position_code'] = (string) ($first['code'] ?? '');
            $data['position_name'] = (string) ($first['name'] ?? '');

            return $data;
        }

        // Legacy single position fields
        $code = trim((string) ($data['position_code'] ?? ''));
        if ($code !== '') {
            $pos = $this->jobCatalog->findTitleByCode($code) ?? $this->positions->findByCode($code);
            if ($pos !== null) {
                $data['position_code'] = $pos['code'];
                $data['position_name'] = $pos['name'];
            }
        } else {
            $name = trim((string) ($data['position_name'] ?? ''));
            if ($name !== '') {
                $data['position_code'] = HrmJobCatalogDirectory::codeFromName('TITLE', $name);
                $data['position_name'] = $name;
            } else {
                $data['position_code'] = null;
                $data['position_name'] = null;
            }
        }

        unset($ranks);

        return $data;
    }

    /**
     * @param  array{titles?: list<array<string, mixed>>, ranks?: list<array<string, mixed>>, criteria?: list<array<string, mixed>>, fields?: list<array<string, mixed>>}  $extras
     */
    private function syncAllRelations(EvaluationTemplate $template, array $extras): void
    {
        $this->syncTargets($template, $extras['titles'] ?? [], EvaluationTemplateTargetKind::Title);
        $this->syncTargets($template, $extras['ranks'] ?? [], EvaluationTemplateTargetKind::Rank);
        $this->syncCriteria($template, $extras['criteria'] ?? []);
        $this->syncFields($template, $extras['fields'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncTargets(EvaluationTemplate $template, array $rows, EvaluationTemplateTargetKind $kind): void
    {
        $template->targets()->where('kind', $kind->value)->delete();

        $seen = [];
        $order = 0;
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $resolved = $kind === EvaluationTemplateTargetKind::Title
                ? ($this->jobCatalog->findTitleByCode($code) ?? ['code' => $code, 'name' => $name, 'source' => $row['source'] ?? 'manual', 'hrm_uuid' => $row['hrm_uuid'] ?? null])
                : ($this->jobCatalog->findRankByCode($code) ?? ['code' => $code, 'name' => $name, 'source' => $row['source'] ?? 'manual', 'hrm_uuid' => $row['hrm_uuid'] ?? null]);

            $template->targets()->create([
                'kind' => $kind->value,
                'code' => $resolved['code'],
                'name' => $resolved['name'],
                'hrm_uuid' => $resolved['hrm_uuid'] ?? ($row['hrm_uuid'] ?? null),
                'source' => $resolved['source'] ?? ($row['source'] ?? 'directory'),
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $criteria
     */
    private function syncCriteria(EvaluationTemplate $template, array $criteria): void
    {
        $template->templateCriteria()->delete();
        $template->customCriteria()->delete();

        $seenCatalog = [];
        $order = 0;
        foreach ($criteria as $line) {
            $source = $line['source'] ?? (! empty($line['criterion_id']) ? 'catalog' : 'custom');
            $weight = isset($line['weight']) ? (float) $line['weight'] : 1;
            $required = filled($line['required_score_label'] ?? null)
                ? trim((string) $line['required_score_label'])
                : null;
            $include = (bool) ($line['include_in_total'] ?? true);
            $sort = isset($line['sort_order']) ? (int) $line['sort_order'] : $order;

            if ($source === 'catalog') {
                $criterionId = (int) ($line['criterion_id'] ?? 0);
                if ($criterionId < 1 || isset($seenCatalog[$criterionId])) {
                    continue;
                }
                $seenCatalog[$criterionId] = true;
                $template->templateCriteria()->create([
                    'criterion_id' => $criterionId,
                    'weight' => $weight,
                    'required_score_label' => $required,
                    'include_in_total' => $include,
                    'sort_order' => $sort,
                ]);
            } else {
                $name = trim((string) ($line['custom_name'] ?? $line['name'] ?? $line['criteria_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $template->customCriteria()->create([
                    'custom_name' => $name,
                    'custom_code' => filled($line['custom_code'] ?? null) ? trim((string) $line['custom_code']) : null,
                    'custom_category' => filled($line['custom_category'] ?? $line['group_label'] ?? null)
                        ? trim((string) ($line['custom_category'] ?? $line['group_label']))
                        : null,
                    'custom_description' => filled($line['custom_description'] ?? $line['description'] ?? null)
                        ? trim((string) ($line['custom_description'] ?? $line['description']))
                        : null,
                    'weight' => $weight,
                    'required_score_label' => $required,
                    'include_in_total' => $include,
                    'sort_order' => $sort,
                ]);
            }
            $order++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function syncFields(EvaluationTemplate $template, array $fields): void
    {
        $template->fields()->delete();

        $seen = [];
        $order = 0;
        foreach ($fields as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = (string) ($field['field_type'] ?? EvaluationTemplateFieldType::Text->value);
            if (! in_array($type, EvaluationTemplateFieldType::values(), true)) {
                $type = EvaluationTemplateFieldType::Text->value;
            }

            $key = trim((string) ($field['field_key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($label, '_');
                if ($key === '') {
                    $key = 'field_'.$order;
                }
            }
            $base = $key;
            $n = 2;
            while (isset($seen[$key])) {
                $key = $base.'_'.$n;
                $n++;
            }
            $seen[$key] = true;

            $options = $field['options'] ?? null;
            if (is_string($options)) {
                $options = array_values(array_filter(array_map('trim', preg_split('/[\n;,]+/', $options) ?: [])));
            }
            if (! is_array($options)) {
                $options = null;
            }

            $template->fields()->create([
                'field_key' => $key,
                'label' => $label,
                'field_type' => $type,
                'options' => $type === EvaluationTemplateFieldType::Select->value ? array_values($options ?? []) : null,
                'is_required' => (bool) ($field['is_required'] ?? $field['required'] ?? false),
                'placeholder' => filled($field['placeholder'] ?? null) ? trim((string) $field['placeholder']) : null,
                'help_text' => filled($field['help_text'] ?? null) ? trim((string) $field['help_text']) : null,
                'sort_order' => isset($field['sort_order']) ? (int) $field['sort_order'] : $order,
            ]);
            $order++;
        }
    }

    /**
     * Shared catalog props for create/edit forms.
     *
     * @return array{jobTitles: list<array<string, mixed>>, jobRanks: list<array<string, mixed>>, fieldTypeOptions: list<array{value: string, label: string}>, criteriaOptions: list<array<string, mixed>>}
     */
    private function formCatalogProps(): array
    {
        return [
            'jobTitles' => $this->jobCatalog->titles(),
            'jobRanks' => $this->jobCatalog->ranks(),
            'fieldTypeOptions' => EvaluationTemplateFieldType::options(),
            'criteriaOptions' => $this->criteriaOptions(),
        ];
    }

    /**
     * @return list<array{id: int, criteria_code: string, criteria_name: string, category: string, scope: string, department_name: string|null, score_levels: list<array{code: string, label: string, description: string, weight: int|float}>}>
     */
    private function criteriaOptions(): array
    {
        return EvaluationCriterion::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'criteria_code', 'criteria_name', 'category', 'scope', 'department_name', 'score_levels', 'allow_half_score'])
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
    private function exportLogsPayload(): array
    {
        return EvaluationTemplateExportLog::query()
            ->with(['exporter:id,display_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (EvaluationTemplateExportLog $log) => $this->formatExportLog($log))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExportLog(EvaluationTemplateExportLog $log): array
    {
        return [
            'id' => $log->id,
            'scope' => $log->scope,
            'format' => $log->format,
            'row_count' => $log->row_count,
            'columns' => $log->columns ?? [],
            'filters' => $log->filters,
            'filename' => $log->filename,
            'exporter_name' => $log->exporter?->display_name ?? 'Hệ thống',
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityFor(EvaluationTemplate $template): array
    {
        return SecurityAuditLog::query()
            ->with(['actor:id,display_name,employee_id', 'actor.employee:id,avatar_path'])
            ->where('subject_type', 'evaluation_template')
            ->where('subject_id', $template->id)
            ->where('action', 'like', 'evaluation.template_%')
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
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function templateAuditMeta(EvaluationTemplate $template): array
    {
        $catalog = $template->relationLoaded('templateCriteria')
            ? $template->templateCriteria->count()
            : $template->templateCriteria()->count();
        $custom = $template->relationLoaded('customCriteria')
            ? $template->customCriteria->count()
            : $template->customCriteria()->count();

        return [
            'template_code' => $template->template_code,
            'name' => $template->name,
            'position_code' => $template->position_code,
            'position_name' => $template->position_name,
            'criteria_count' => $catalog + $custom,
            'fields_count' => $template->relationLoaded('fields')
                ? $template->fields->count()
                : $template->fields()->count(),
            'is_active' => (bool) $template->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(EvaluationTemplate $template): array
    {
        $template->loadMissing([
            'templateCriteria.criterion:id,criteria_code,criteria_name',
            'customCriteria',
            'targets',
            'fields',
        ]);

        $criteriaSummary = collect()
            ->merge($template->templateCriteria->map(fn ($line) => ($line->criterion?->criteria_code ?? '#'.$line->criterion_id).'×'.($line->weight ?? 1)))
            ->merge($template->customCriteria->map(fn ($line) => 'custom:'.$line->custom_name.'×'.($line->weight ?? 1)))
            ->implode(', ');

        $targetSummary = $template->targets
            ->map(fn ($t) => ($t->kind instanceof EvaluationTemplateTargetKind ? $t->kind->value : $t->kind).':'.$t->name)
            ->implode(', ');

        $fieldsSummary = $template->fields
            ->map(fn ($f) => $f->label.'('.(
                $f->field_type instanceof EvaluationTemplateFieldType
                    ? $f->field_type->value
                    : $f->field_type
            ).')')
            ->implode(', ');

        return [
            'name' => $template->name,
            'description' => $template->description,
            'position_name' => $targetSummary !== '' ? $targetSummary : $template->position_name,
            'is_active' => (bool) $template->is_active,
            'criteria_summary' => $criteriaSummary,
            'fields_summary' => $fieldsSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{label: string, from?: string, to?: string}>
     */
    private function diffTemplateSnapshot(array $before, array $after): array
    {
        $labels = [
            'name' => 'Tên mẫu',
            'description' => 'Mô tả',
            'position_name' => 'Vị trí đánh giá',
            'is_active' => 'Trạng thái',
            'criteria_summary' => 'Tiêu chí gắn',
            'fields_summary' => 'Trường tùy biến',
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
        if ($key === 'is_active') {
            return $value ? 'Đang hoạt động' : 'Ngưng hoạt động';
        }
        if ($value === null || $value === '') {
            return 'Chưa cập nhật';
        }

        return (string) $value;
    }
}
