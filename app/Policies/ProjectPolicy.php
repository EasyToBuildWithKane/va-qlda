<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Project\ProjectVisibility;

class ProjectPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Project $project): bool
    {
        return ProjectVisibility::canView($account, $project);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('project.create');
    }

    public function update(SystemAccount $account, Project $project): bool
    {
        return $account->allows('project.update') || $this->isManager($account, $project);
    }

    public function delete(SystemAccount $account, Project $project): bool
    {
        return $account->allows('project.delete');
    }

    /** Add/remove members, set rates, manage sprints/tasks/blockers. */
    public function manage(SystemAccount $account, Project $project): bool
    {
        return $account->allows('project.manage') || $this->isManager($account, $project);
    }

    /** Log work / move own tasks — managers, reviewers, or project members. */
    public function contribute(SystemAccount $account, Project $project): bool
    {
        return $account->allows('project.contribute')
            || $this->manage($account, $project)
            || $this->isMember($account, $project);
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
