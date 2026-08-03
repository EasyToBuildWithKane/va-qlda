<?php

namespace App\Support\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Models\Evaluation\EvaluationFormCriterion;
use App\Models\Evaluation\EvaluationFormRater;
use App\Models\Evaluation\EvaluationFormSubmission;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormRaterRole;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Enums\EvaluationFormSubmissionStatus;
use Illuminate\Support\Collection;

/**
 * Pure helpers for evaluation-form scoring (weights, sequential gates, role mapping).
 */
class EvaluationFormScoringService
{
    /**
     * Map rater role → employee id on an assignee row.
     */
    public function employeeIdForRole(EvaluationFormAssignee $assignee, string $roleKey): ?int
    {
        return match ($roleKey) {
            EvaluationFormRaterRole::Self->value => (int) $assignee->employee_id,
            EvaluationFormRaterRole::DeptHead->value => (int) $assignee->dept_head_employee_id,
            EvaluationFormRaterRole::DirectManager->value => (int) $assignee->direct_manager_employee_id,
            EvaluationFormRaterRole::Board->value => $assignee->board_employee_id
                ? (int) $assignee->board_employee_id
                : null,
            default => null,
        };
    }

    /**
     * Roles the account may score for this assignee (manage = all configured raters with a mapped employee).
     *
     * @return list<string>
     */
    public function scorableRolesFor(
        SystemAccount $account,
        EvaluationForm $form,
        EvaluationFormAssignee $assignee,
    ): array {
        $raters = $form->relationLoaded('raters')
            ? $form->raters
            : $form->raters()->get();

        $employeeId = $account->employee_id ? (int) $account->employee_id : null;
        $canManage = $account->allows('workspace.evaluation.manage');
        $roles = [];

        foreach ($raters as $rater) {
            /** @var EvaluationFormRater $rater */
            $roleKey = (string) $rater->role_key;
            $mapped = $this->employeeIdForRole($assignee, $roleKey);
            if ($mapped === null) {
                continue;
            }
            if ($canManage || ($employeeId !== null && $employeeId === $mapped)) {
                $roles[] = $roleKey;
            }
        }

        return array_values(array_unique($roles));
    }

    public function canScoreRole(
        SystemAccount $account,
        EvaluationForm $form,
        EvaluationFormAssignee $assignee,
        string $roleKey,
        bool $allowResubmit = false,
    ): bool {
        if ($form->status !== EvaluationFormStatus::Active) {
            return false;
        }

        if (! in_array($roleKey, $this->scorableRolesFor($account, $form, $assignee), true)) {
            return false;
        }

        if (! $this->sequentialGateOpen($form, $assignee, $roleKey)) {
            return false;
        }

        if ($allowResubmit || $account->allows('workspace.evaluation.manage')) {
            return true;
        }

        $existing = EvaluationFormSubmission::query()
            ->where('form_id', $form->id)
            ->where('assignee_id', $assignee->id)
            ->where('rater_role_key', $roleKey)
            ->first();

        if ($existing && $existing->isSubmitted()) {
            return false;
        }

        return true;
    }

    /**
     * For sequential order: prior raters (lower sort_order) must be submitted.
     */
    public function sequentialGateOpen(
        EvaluationForm $form,
        EvaluationFormAssignee $assignee,
        string $roleKey,
    ): bool {
        $order = $form->evaluation_order instanceof EvaluationFormOrder
            ? $form->evaluation_order
            : EvaluationFormOrder::tryFrom((string) $form->evaluation_order);

        if ($order !== EvaluationFormOrder::Sequential) {
            return true;
        }

        $raters = ($form->relationLoaded('raters') ? $form->raters : $form->raters()->get())
            ->sortBy(['sort_order', 'id'])
            ->values();

        $target = $raters->firstWhere('role_key', $roleKey);
        if (! $target) {
            return false;
        }

        $submissions = EvaluationFormSubmission::query()
            ->where('form_id', $form->id)
            ->where('assignee_id', $assignee->id)
            ->where('status', EvaluationFormSubmissionStatus::Submitted)
            ->get()
            ->keyBy('rater_role_key');

        foreach ($raters as $rater) {
            if ((int) $rater->sort_order >= (int) $target->sort_order) {
                break;
            }
            if (! $submissions->has($rater->role_key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Criteria this role is allowed to score on a form.
     *
     * @return Collection<int, EvaluationFormCriterion>
     */
    public function criteriaForRole(EvaluationForm $form, string $roleKey): Collection
    {
        $criteria = $form->relationLoaded('criteria')
            ? $form->criteria
            : $form->criteria()->get();

        return $criteria->filter(function (EvaluationFormCriterion $c) use ($roleKey) {
            $mode = (string) ($c->evaluator_mode ?: 'all');
            if ($mode === 'all') {
                return true;
            }
            $keys = is_array($c->evaluator_role_keys) ? $c->evaluator_role_keys : [];

            return in_array($roleKey, $keys, true);
        })->values();
    }

    /**
     * @param  list<array{form_criterion_id: int, score_weight: float|int}>  $lines
     * @param  Collection<int, EvaluationFormCriterion>|iterable<EvaluationFormCriterion>  $criteria
     */
    public function computeSubmissionTotal(array $lines, iterable $criteria): float
    {
        $byId = collect($criteria)->keyBy('id');
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($lines as $line) {
            $criterionId = (int) ($line['form_criterion_id'] ?? 0);
            /** @var EvaluationFormCriterion|null $criterion */
            $criterion = $byId->get($criterionId);
            if (! $criterion) {
                continue;
            }
            $cw = (float) $criterion->weight;
            if ($cw <= 0) {
                continue;
            }
            $sw = (float) ($line['score_weight'] ?? 0);
            $weightedSum += $cw * $sw;
            $weightTotal += $cw;
        }

        if ($weightTotal <= 0) {
            return 0.0;
        }

        return round($weightedSum / $weightTotal, 2);
    }

    /**
     * Aggregate assignee score from submitted rater totals.
     *
     * @param  Collection<int, EvaluationFormSubmission>|iterable<EvaluationFormSubmission>  $submissions
     * @param  Collection<int, EvaluationFormRater>|iterable<EvaluationFormRater>  $raters
     */
    public function computeAssigneeAggregate(iterable $submissions, iterable $raters, bool $useWeight): ?float
    {
        $submitted = collect($submissions)
            ->filter(fn (EvaluationFormSubmission $s) => $s->isSubmitted() && $s->total_score !== null);

        if ($submitted->isEmpty()) {
            return null;
        }

        if (! $useWeight) {
            return round((float) $submitted->avg('total_score'), 2);
        }

        $raterWeights = collect($raters)->keyBy('role_key');
        $sum = 0.0;
        $wSum = 0.0;

        foreach ($submitted as $sub) {
            /** @var EvaluationFormRater|null $rater */
            $rater = $raterWeights->get($sub->rater_role_key);
            $w = $rater ? (float) $rater->weight_percent : 0.0;
            if ($w <= 0) {
                continue;
            }
            $sum += (float) $sub->total_score * $w;
            $wSum += $w;
        }

        if ($wSum <= 0) {
            return round((float) $submitted->avg('total_score'), 2);
        }

        return round($sum / $wSum, 2);
    }

    public function canOpen(SystemAccount $account, EvaluationForm $form): bool
    {
        return $account->allows('workspace.evaluation.manage')
            && $form->status === EvaluationFormStatus::Draft;
    }

    public function canClose(SystemAccount $account, EvaluationForm $form): bool
    {
        if ($form->status !== EvaluationFormStatus::Active) {
            return false;
        }

        if ($account->allows('workspace.evaluation.manage')) {
            return true;
        }

        $employeeId = $account->employee_id ? (int) $account->employee_id : null;

        return $employeeId !== null && $employeeId === (int) $form->manager_employee_id;
    }

    public function canReopen(SystemAccount $account, EvaluationForm $form): bool
    {
        return $account->allows('workspace.evaluation.manage')
            && $form->status === EvaluationFormStatus::Closed;
    }
}
