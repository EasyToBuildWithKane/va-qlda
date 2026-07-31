<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationTemplate;
use App\Models\SystemAccount;

class EvaluationTemplatePolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.view')
            || $account->allows('workspace.evaluation.manage')
            || $account->allows('workspace.hub.view');
    }

    public function view(SystemAccount $account, EvaluationTemplate $template): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function update(SystemAccount $account, EvaluationTemplate $template): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }

    public function delete(SystemAccount $account, EvaluationTemplate $template): bool
    {
        return $account->allows('workspace.evaluation.manage');
    }
}
