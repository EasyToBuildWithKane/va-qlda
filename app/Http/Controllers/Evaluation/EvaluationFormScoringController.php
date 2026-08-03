<?php

namespace App\Http\Controllers\Evaluation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\SaveEvaluationFormScoresRequest;
use App\Http\Requests\Evaluation\SubmitEvaluationFormScoresRequest;
use App\Http\Resources\Evaluation\EvaluationFormResource;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Models\Evaluation\EvaluationFormSubmission;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Enums\EvaluationFormSubmissionStatus;
use App\Support\Evaluation\EvaluationFormScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationFormScoringController extends Controller
{
    public function __construct(
        private readonly EvaluationFormScoringService $scoring,
    ) {}

    public function open(Request $request, EvaluationForm $evaluationForm): RedirectResponse
    {
        $this->authorize('open', $evaluationForm);

        $evaluationForm->update(['status' => EvaluationFormStatus::Active]);

        return redirect()
            ->route('workspace.evaluation-forms.scoring.index', $evaluationForm)
            ->with('success', 'Đã mở phiếu để chấm điểm.');
    }

    public function close(Request $request, EvaluationForm $evaluationForm): RedirectResponse
    {
        $this->authorize('close', $evaluationForm);

        $evaluationForm->update(['status' => EvaluationFormStatus::Closed]);

        return back()->with('success', 'Đã khóa kỳ đánh giá.');
    }

    public function reopen(Request $request, EvaluationForm $evaluationForm): RedirectResponse
    {
        $this->authorize('reopen', $evaluationForm);

        $evaluationForm->update(['status' => EvaluationFormStatus::Active]);

        return back()->with('success', 'Đã mở lại phiếu đánh giá.');
    }

    public function index(Request $request, EvaluationForm $evaluationForm): Response
    {
        $this->authorize('viewScoring', $evaluationForm);

        $evaluationForm->load([
            'raters',
            'assignees.employee:id,full_name,code',
            'criteria',
            'submissions.lines',
        ]);

        $user = $request->user();
        $progress = $this->buildProgressMatrix($evaluationForm, $user);

        return Inertia::render('WorkspaceConfig/EvaluationForms/Scoring/Index', [
            'form' => (new EvaluationFormResource($evaluationForm))->resolve(),
            'raters' => $evaluationForm->raters->map(fn ($r) => [
                'role_key' => $r->role_key,
                'label' => $r->label,
                'weight_percent' => (float) $r->weight_percent,
                'sort_order' => $r->sort_order,
            ])->values()->all(),
            'progress' => $progress,
            'can' => [
                'manage' => $user->can('update', $evaluationForm),
                'close' => $user->can('close', $evaluationForm),
                'reopen' => $user->can('reopen', $evaluationForm),
                'score' => $evaluationForm->status === EvaluationFormStatus::Active,
            ],
        ]);
    }

    public function show(
        Request $request,
        EvaluationForm $evaluationForm,
        EvaluationFormAssignee $assignee,
    ): Response {
        abort_unless($assignee->form_id === $evaluationForm->id, 404);
        $this->authorize('viewScoring', $evaluationForm);

        $evaluationForm->load([
            'raters',
            'criteria.criterion:id,criteria_code,criteria_name,score_levels,allow_half_score',
            'fields',
        ]);

        $user = $request->user();
        $scorableRoles = $this->scoring->scorableRolesFor($user, $evaluationForm, $assignee);

        $roleKey = trim((string) $request->query('role', ''));
        if ($roleKey === '' || ! in_array($roleKey, $scorableRoles, true)) {
            $roleKey = $scorableRoles[0] ?? '';
        }

        abort_if($roleKey === '', 403, 'Bạn không có vai trò chấm điểm trên nhân sự này.');

        $canWrite = $this->scoring->canScoreRole($user, $evaluationForm, $assignee, $roleKey);
        $criteria = $this->scoring->criteriaForRole($evaluationForm, $roleKey);

        $submission = EvaluationFormSubmission::query()
            ->where('form_id', $evaluationForm->id)
            ->where('assignee_id', $assignee->id)
            ->where('rater_role_key', $roleKey)
            ->with(['lines', 'fieldValues'])
            ->first();

        $linesByCriterion = collect($submission?->lines ?? [])->keyBy('form_criterion_id');
        $fieldsById = collect($submission?->fieldValues ?? [])->keyBy('form_field_id');

        $criteriaPayload = $criteria->map(function ($c) use ($linesByCriterion) {
            $catalog = $c->relationLoaded('criterion') ? $c->criterion : null;
            $levels = $catalog instanceof EvaluationCriterion
                ? $catalog->normalizedScoreLevels()
                : [];
            $line = $linesByCriterion->get($c->id);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'weight' => (float) $c->weight,
                'required_score_label' => $c->required_score_label,
                'score_levels' => $levels,
                'selected_code' => $line?->score_level_code,
                'selected_label' => $line?->score_level_label,
                'selected_weight' => $line?->score_weight,
            ];
        })->values()->all();

        $fieldsPayload = $evaluationForm->fields
            ->filter(fn ($f) => (bool) $f->is_enabled)
            ->map(function ($f) use ($fieldsById) {
                return [
                    'id' => $f->id,
                    'field_key' => $f->field_key,
                    'label' => $f->label,
                    'field_type' => $f->field_type,
                    'value' => $fieldsById->get($f->id)?->value,
                ];
            })->values()->all();

        return Inertia::render('WorkspaceConfig/EvaluationForms/Scoring/Show', [
            'form' => [
                'id' => $evaluationForm->id,
                'name' => $evaluationForm->name,
                'form_code' => $evaluationForm->form_code,
                'status' => $evaluationForm->status instanceof EvaluationFormStatus
                    ? $evaluationForm->status->value
                    : (string) $evaluationForm->status,
                'status_label' => $evaluationForm->status instanceof EvaluationFormStatus
                    ? $evaluationForm->status->label()
                    : null,
                'use_weight' => (bool) $evaluationForm->use_weight,
                'evaluation_order' => $evaluationForm->evaluation_order?->value
                    ?? (string) $evaluationForm->evaluation_order,
            ],
            'assignee' => [
                'id' => $assignee->id,
                'employee_id' => $assignee->employee_id,
                'employee_name' => $assignee->employee_name,
                'employee_code' => $assignee->employee_code,
                'department_name' => $assignee->department_name,
            ],
            'roleKey' => $roleKey,
            'scorableRoles' => collect($scorableRoles)->map(function ($key) use ($evaluationForm) {
                $rater = $evaluationForm->raters->firstWhere('role_key', $key);

                return [
                    'value' => $key,
                    'label' => $rater?->label ?? $key,
                ];
            })->values()->all(),
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status instanceof EvaluationFormSubmissionStatus
                    ? $submission->status->value
                    : (string) $submission->status,
                'status_label' => $submission->status instanceof EvaluationFormSubmissionStatus
                    ? $submission->status->label()
                    : null,
                'total_score' => $submission->total_score,
                'comment' => $submission->comment,
                'submitted_at' => optional($submission->submitted_at)?->toIso8601String(),
            ] : null,
            'criteria' => $criteriaPayload,
            'fields' => $fieldsPayload,
            'can' => [
                'write' => $canWrite,
                'submit' => $canWrite,
            ],
            'sequentialBlocked' => ! $this->scoring->sequentialGateOpen($evaluationForm, $assignee, $roleKey)
                && in_array($roleKey, $scorableRoles, true),
        ]);
    }

    public function save(
        SaveEvaluationFormScoresRequest $request,
        EvaluationForm $evaluationForm,
        EvaluationFormAssignee $assignee,
    ): RedirectResponse {
        abort_unless($assignee->form_id === $evaluationForm->id, 404);

        $this->persistScores($request, $evaluationForm, $assignee, submit: false);

        return back()->with('success', 'Đã lưu nháp điểm đánh giá.');
    }

    public function submit(
        SubmitEvaluationFormScoresRequest $request,
        EvaluationForm $evaluationForm,
        EvaluationFormAssignee $assignee,
    ): RedirectResponse {
        abort_unless($assignee->form_id === $evaluationForm->id, 404);

        $this->persistScores($request, $evaluationForm, $assignee, submit: true);

        return redirect()
            ->route('workspace.evaluation-forms.scoring.index', $evaluationForm)
            ->with('success', 'Đã nộp phiếu đánh giá.');
    }

    private function persistScores(
        Request $request,
        EvaluationForm $evaluationForm,
        EvaluationFormAssignee $assignee,
        bool $submit,
    ): EvaluationFormSubmission {
        $validated = $request->validated();
        $roleKey = (string) $validated['rater_role_key'];
        $raterEmployeeId = $this->scoring->employeeIdForRole($assignee, $roleKey);
        abort_if($raterEmployeeId === null, 422, 'Không xác định được người chấm cho vai trò này.');

        $evaluationForm->loadMissing(['criteria.criterion', 'fields']);
        $allowedCriteria = $this->scoring->criteriaForRole($evaluationForm, $roleKey);

        return DB::transaction(function () use (
            $validated,
            $evaluationForm,
            $assignee,
            $roleKey,
            $raterEmployeeId,
            $allowedCriteria,
            $submit,
            $request,
        ) {
            /** @var EvaluationFormSubmission $submission */
            $submission = EvaluationFormSubmission::query()->updateOrCreate(
                [
                    'form_id' => $evaluationForm->id,
                    'assignee_id' => $assignee->id,
                    'rater_role_key' => $roleKey,
                ],
                [
                    'rater_employee_id' => $raterEmployeeId,
                    'comment' => $validated['comment'] ?? null,
                ]
            );

            if ($submission->isSubmitted() && ! $submit) {
                // Keep submitted locked for draft save — shouldn't reach here if canScore checks status
            }

            $submission->lines()->delete();
            $linesPayload = [];
            foreach ($validated['lines'] ?? [] as $line) {
                $criterionId = (int) $line['form_criterion_id'];
                if (! $allowedCriteria->contains('id', $criterionId)) {
                    continue;
                }
                $weight = (float) ($line['score_weight'] ?? 0);
                $submission->lines()->create([
                    'form_criterion_id' => $criterionId,
                    'score_level_code' => $line['score_level_code'] ?? null,
                    'score_level_label' => $line['score_level_label'] ?? null,
                    'score_weight' => $weight,
                ]);
                $linesPayload[] = [
                    'form_criterion_id' => $criterionId,
                    'score_weight' => $weight,
                ];
            }

            $submission->fieldValues()->delete();
            $enabledFieldIds = $evaluationForm->fields
                ->filter(fn ($f) => (bool) $f->is_enabled)
                ->pluck('id')
                ->all();
            foreach ($validated['field_values'] ?? [] as $fv) {
                $fieldId = (int) $fv['form_field_id'];
                if (! in_array($fieldId, $enabledFieldIds, true)) {
                    continue;
                }
                $submission->fieldValues()->create([
                    'form_field_id' => $fieldId,
                    'value' => $fv['value'] ?? null,
                ]);
            }

            $total = $this->scoring->computeSubmissionTotal($linesPayload, $allowedCriteria);

            $updates = ['total_score' => $total];
            if ($submit) {
                $updates['status'] = EvaluationFormSubmissionStatus::Submitted;
                $updates['submitted_at'] = now();
                $updates['submitted_by'] = $request->user()->id;
            } else {
                $updates['status'] = EvaluationFormSubmissionStatus::Draft;
                $updates['submitted_at'] = null;
                $updates['submitted_by'] = null;
            }

            $submission->update($updates);

            return $submission->fresh(['lines', 'fieldValues']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildProgressMatrix(EvaluationForm $form, $user): array
    {
        $submissions = $form->submissions->groupBy('assignee_id');

        return $form->assignees->map(function (EvaluationFormAssignee $assignee) use ($form, $submissions, $user) {
            $byRole = ($submissions->get($assignee->id) ?? collect())->keyBy('rater_role_key');
            $roles = [];
            foreach ($form->raters as $rater) {
                $sub = $byRole->get($rater->role_key);
                $canScore = $this->scoring->canScoreRole($user, $form, $assignee, $rater->role_key);
                $roles[] = [
                    'role_key' => $rater->role_key,
                    'label' => $rater->label,
                    'status' => $sub
                        ? ($sub->status instanceof EvaluationFormSubmissionStatus
                            ? $sub->status->value
                            : (string) $sub->status)
                        : null,
                    'status_label' => $sub && $sub->status instanceof EvaluationFormSubmissionStatus
                        ? $sub->status->label()
                        : ($sub ? (string) $sub->status : 'Chưa chấm'),
                    'total_score' => $sub?->total_score,
                    'can_score' => $canScore,
                ];
            }

            $submittedSubs = ($submissions->get($assignee->id) ?? collect())
                ->filter(fn ($s) => $s->isSubmitted());
            $aggregate = $this->scoring->computeAssigneeAggregate(
                $submittedSubs,
                $form->raters,
                (bool) $form->use_weight,
            );

            $scorable = $this->scoring->scorableRolesFor($user, $form, $assignee);

            return [
                'assignee_id' => $assignee->id,
                'employee_name' => $assignee->employee_name,
                'employee_code' => $assignee->employee_code,
                'department_name' => $assignee->department_name,
                'roles' => $roles,
                'aggregate_score' => $aggregate,
                'can_open_scoring' => $scorable !== [] && $form->status === EvaluationFormStatus::Active,
                'default_role' => $scorable[0] ?? null,
            ];
        })->values()->all();
    }
}
