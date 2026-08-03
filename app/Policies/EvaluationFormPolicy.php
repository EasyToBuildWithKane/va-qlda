<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationForm;
use App\Models\SystemAccount;

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
}
