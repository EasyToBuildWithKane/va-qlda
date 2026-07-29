<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationConfig;
use App\Models\SystemAccount;

class EvaluationConfigPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.view')
            || $account->allows('workspace.evaluation.manage');
    }

    public function view(SystemAccount $account, EvaluationConfig $config): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function update(SystemAccount $account, EvaluationConfig $config): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function delete(SystemAccount $account, EvaluationConfig $config): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }
}
