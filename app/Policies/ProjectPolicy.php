<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class ProjectPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Project $project): bool
    {
        return $this->isReviewer($account)
            || $account->role === SystemRole::Viewer
            || $this->isManager($account, $project)
            || $this->isMember($account, $project);
    }

    public function create(SystemAccount $account): bool
    {
        return $this->isReviewer($account);
    }

    public function update(SystemAccount $account, Project $project): bool
    {
        return $this->isReviewer($account) || $this->isManager($account, $project);
    }

    public function delete(SystemAccount $account, Project $project): bool
    {
        return $account->role === SystemRole::Admin;
    }

    /** Add/remove members, set rates, manage sprints/tasks/blockers. */
    public function manage(SystemAccount $account, Project $project): bool
    {
        return $this->isReviewer($account) || $this->isManager($account, $project);
    }

    /** Log work / move own tasks — managers, reviewers, or project members. */
    public function contribute(SystemAccount $account, Project $project): bool
    {
        return $this->manage($account, $project) || $this->isMember($account, $project);
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    private function isManager(SystemAccount $account, Project $project): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $project->manager_id;
    }

    private function isMember(SystemAccount $account, Project $project): bool
    {
        return $account->employee_id !== null
            && $project->members()->where('employee_id', $account->employee_id)->exists();
    }
}
