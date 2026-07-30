<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SystemAccount;

class EvaluationCriterionPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.view')
            || $account->allows('workspace.evaluation.manage');
    }

    public function view(SystemAccount $account, EvaluationCriterion $criterion): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function update(SystemAccount $account, EvaluationCriterion $criterion): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function delete(SystemAccount $account, EvaluationCriterion $criterion): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }
}
