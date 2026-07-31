<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\ImportEvaluationTemplateRequest;
use App\Http\Requests\Evaluation\RecordEvaluationTemplateExportRequest;
use App\Http\Requests\Evaluation\StoreEvaluationTemplateRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationTemplateRequest;
use App\Http\Resources\Evaluation\EvaluationTemplateResource;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Evaluation\EvaluationTemplateExportLog;
use App\Models\SecurityAuditLog;
use App\Services\NotificationService;
use App\Support\Audit\AuditActionCatalog;
use App\Support\Enums\NotificationType;
use App\Support\Evaluation\HrmPositionDirectory;
use App\Support\PublicMediaUrl;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationTemplateController extends Controller
{
    public function __construct(
        private readonly HrmPositionDirectory $positions,
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
            ])
            ->withCount('templateCriteria')
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
            $query->where('position_code', $filters['position_code']);
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
            'criteriaOptions' => $this->criteriaOptions(),
            'nextCode' => EvaluationTemplate::suggestNextCode(),
            'exportLogs' => $this->exportLogsPayload(),
            'can' => [
                'manage' => $user->can('create', EvaluationTemplate::class),
            ],
        ]);
    }

    public function store(StoreEvaluationTemplateRequest $request): RedirectResponse
    {
        $data = $this->normalizePayload($request->validated());
        $criteria = $data['criteria'] ?? [];
        unset($data['criteria']);

        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? ((int) EvaluationTemplate::query()->max('sort_order') + 1);

        if (! filled($data['template_code'] ?? null)) {
            $data['template_code'] = EvaluationTemplate::suggestNextCode();
        }

        $template = DB::transaction(function () use ($data, $criteria) {
            $template = EvaluationTemplate::query()->create($data);
            $this->syncCriteria($template, $criteria);

            return $template;
        });

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_created',
            $template->id,
            $this->templateAuditMeta($template->fresh(['templateCriteria']))
        );

        return back()->with('success', 'Đã tạo mẫu đánh giá.');
    }

    public function import(ImportEvaluationTemplateRequest $request): RedirectResponse
    {
        $rows = $request->validated('rows');
        $account = $request->user();
        $created = 0;

        DB::transaction(function () use ($rows, $account, &$created) {
            foreach ($rows as $row) {
                $data = $this->normalizePayload($row);
                $criteria = $data['criteria'] ?? [];
                unset($data['criteria']);

                $data['created_by'] = $account->id;
                $data['is_active'] = $data['is_active'] ?? true;
                $data['sort_order'] = (int) EvaluationTemplate::query()->max('sort_order') + 1;

                if (! filled($data['template_code'] ?? null)) {
                    $data['template_code'] = EvaluationTemplate::suggestNextCode();
                }

                $template = EvaluationTemplate::query()->create($data);
                $this->syncCriteria($template, $criteria);

                SecurityAuditLogger::evaluationTemplate(
                    $account,
                    'template_created',
                    $template->id,
                    $this->templateAuditMeta($template->fresh(['templateCriteria']))
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
        ]);

        $user = $request->user();

        return Inertia::render('WorkspaceConfig/EvaluationTemplates/Show', [
            'template' => (new EvaluationTemplateResource($evaluationTemplate))->resolve(),
            'activity' => $this->activityFor($evaluationTemplate),
            'positions' => $this->positions->all(),
            'criteriaOptions' => $this->criteriaOptions(),
            'can' => [
                'manage' => $user->can('update', $evaluationTemplate),
            ],
        ]);
    }

    public function update(
        UpdateEvaluationTemplateRequest $request,
        EvaluationTemplate $evaluationTemplate,
    ): RedirectResponse {
        $before = $this->templateSnapshot($evaluationTemplate);
        $data = $this->normalizePayload($request->validated());
        $criteria = $data['criteria'] ?? [];
        unset($data['criteria']);

        if (! filled($data['template_code'] ?? null)) {
            $data['template_code'] = $evaluationTemplate->template_code;
        }

        DB::transaction(function () use ($evaluationTemplate, $data, $criteria) {
            $evaluationTemplate->update($data);
            $this->syncCriteria($evaluationTemplate, $criteria);
        });

        $evaluationTemplate->refresh()->load('templateCriteria');

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

        $evaluationTemplate->load('templateCriteria');

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

            $lines = $evaluationTemplate->templateCriteria->map(fn ($line) => [
                'criterion_id' => $line->criterion_id,
                'weight' => $line->weight,
                'required_score_label' => $line->required_score_label,
                'include_in_total' => $line->include_in_total,
                'sort_order' => $line->sort_order,
            ])->all();

            $this->syncCriteria($copy, $lines);

            return $copy;
        });

        SecurityAuditLogger::evaluationTemplate(
            $request->user(),
            'template_duplicated',
            $copy->id,
            array_merge($this->templateAuditMeta($copy->fresh(['templateCriteria'])), [
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

        if ($request->wantsJson() || $request->header('X-Inertia') === null && $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'log' => $this->formatExportLog($log->load('exporter:id,display_name')),
            ]);
        }

        return back()->with('success', 'Đã ghi lịch sử xuất mẫu đánh giá.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        $code = trim((string) ($data['position_code'] ?? ''));
        if ($code !== '') {
            $pos = $this->positions->findByCode($code);
            if ($pos !== null) {
                $data['position_code'] = $pos['code'];
                $data['position_name'] = $pos['name'];
            } elseif (! filled($data['position_name'] ?? null)) {
                $byName = $this->positions->findByName($code);
                if ($byName !== null) {
                    $data['position_code'] = $byName['code'];
                    $data['position_name'] = $byName['name'];
                }
            }
        } else {
            $name = trim((string) ($data['position_name'] ?? ''));
            if ($name !== '') {
                $pos = $this->positions->findByName($name);
                if ($pos !== null) {
                    $data['position_code'] = $pos['code'];
                    $data['position_name'] = $pos['name'];
                } else {
                    $data['position_code'] = HrmPositionDirectory::codeFromName($name);
                    $data['position_name'] = $name;
                }
            } else {
                $data['position_code'] = null;
                $data['position_name'] = null;
            }
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $criteria
     */
    private function syncCriteria(EvaluationTemplate $template, array $criteria): void
    {
        $template->templateCriteria()->delete();

        $seen = [];
        $order = 0;
        foreach ($criteria as $line) {
            $criterionId = (int) ($line['criterion_id'] ?? 0);
            if ($criterionId < 1 || isset($seen[$criterionId])) {
                continue;
            }
            $seen[$criterionId] = true;

            $template->templateCriteria()->create([
                'criterion_id' => $criterionId,
                'weight' => isset($line['weight']) ? (float) $line['weight'] : 1,
                'required_score_label' => filled($line['required_score_label'] ?? null)
                    ? trim((string) $line['required_score_label'])
                    : null,
                'include_in_total' => (bool) ($line['include_in_total'] ?? true),
                'sort_order' => isset($line['sort_order']) ? (int) $line['sort_order'] : $order,
            ]);
            $order++;
        }
    }

    /**
     * @return list<array{id: int, criteria_code: string, criteria_name: string, category: string, scope: string, department_name: string|null}>
     */
    private function criteriaOptions(): array
    {
        return EvaluationCriterion::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'criteria_code', 'criteria_name', 'category', 'scope', 'department_name'])
            ->map(fn (EvaluationCriterion $c) => [
                'id' => $c->id,
                'criteria_code' => $c->criteria_code,
                'criteria_name' => $c->criteria_name,
                'category' => $c->category,
                'scope' => $c->scope instanceof \BackedEnum ? $c->scope->value : (string) $c->scope,
                'department_name' => $c->department_name,
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
        $count = $template->relationLoaded('templateCriteria')
            ? $template->templateCriteria->count()
            : $template->templateCriteria()->count();

        return [
            'template_code' => $template->template_code,
            'name' => $template->name,
            'position_code' => $template->position_code,
            'position_name' => $template->position_name,
            'criteria_count' => $count,
            'is_active' => (bool) $template->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(EvaluationTemplate $template): array
    {
        $template->loadMissing('templateCriteria.criterion:id,criteria_code,criteria_name');

        $criteriaSummary = $template->templateCriteria
            ->map(fn ($line) => ($line->criterion?->criteria_code ?? '#'.$line->criterion_id)
                .'×'.($line->weight ?? 1))
            ->implode(', ');

        return [
            'name' => $template->name,
            'description' => $template->description,
            'position_name' => $template->position_name,
            'is_active' => (bool) $template->is_active,
            'criteria_summary' => $criteriaSummary,
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
