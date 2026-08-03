<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationFormStatus;
use App\Support\Evaluation\EvaluationFormScoringService;

class EvaluationFormPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.view')
            || $account->allows('workspace.evaluation.manage')
            || $account->allows('workspace.hub.view');
    }

    public function view(SystemAccount $account, EvaluationForm $form): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function update(SystemAccount $account, EvaluationForm $form): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function delete(SystemAccount $account, EvaluationForm $form): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function open(SystemAccount $account, EvaluationForm $form): bool
    {
        return app(EvaluationFormScoringService::class)->canOpen($account, $form);
    }

    public function close(SystemAccount $account, EvaluationForm $form): bool
    {
        return app(EvaluationFormScoringService::class)->canClose($account, $form);
    }

    public function reopen(SystemAccount $account, EvaluationForm $form): bool
    {
        return app(EvaluationFormScoringService::class)->canReopen($account, $form);
    }

    public function viewScoring(SystemAccount $account, EvaluationForm $form): bool
    {
        if (! $this->view($account, $form)) {
            return false;
        }

        return in_array($form->status, [EvaluationFormStatus::Active, EvaluationFormStatus::Closed], true)
            || $account->allows('workspace.evaluation.manage');
    }

    public function score(
        SystemAccount $account,
        EvaluationForm $form,
        ?EvaluationFormAssignee $assignee = null,
        ?string $roleKey = null,
    ): bool {
        if ($form->status !== EvaluationFormStatus::Active) {
            return false;
        }

        if ($assignee === null || $roleKey === null) {
            return $account->allows('workspace.evaluation.manage')
                || ($account->employee_id !== null);
        }

        return app(EvaluationFormScoringService::class)
            ->canScoreRole($account, $form, $assignee, $roleKey);
    }
}
