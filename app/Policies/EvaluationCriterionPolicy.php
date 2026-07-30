<?php

namespace App\Policies;

use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;

class EvaluationCriterionPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.evaluation.view')
            || $account->allows('workspace.evaluation.manage')
            || $account->allows('workspace.hub.view');
    }

    public function view(SystemAccount $account, EvaluationCriterion $criterion): bool
    {
        if (! $this->viewAny($account)) {
            return false;
        }

        $scope = app(WorkspaceScopeResolver::class);
        if ($scope->canManageAll($account)
            || $account->allows('workspace.evaluation.manage')
            || $account->allows('workspace.evaluation.view')) {
            return true;
        }

        // Department-scoped readers: general criteria + own department only.
        if ($criterion->scope === EvaluationCriterionScope::General) {
            return true;
        }

        return $scope->canAccess($account, $criterion->department_code);
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
