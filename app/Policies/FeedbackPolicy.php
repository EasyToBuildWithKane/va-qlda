<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class FeedbackPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Feedback $feedback): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->role !== SystemRole::Viewer;
    }

    public function update(SystemAccount $account, Feedback $feedback): bool
    {
        return $this->isReviewer($account) || $this->isAssignee($account, $feedback);
    }

    public function delete(SystemAccount $account, Feedback $feedback): bool
    {
        return $account->role === SystemRole::Admin;
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    private function isAssignee(SystemAccount $account, Feedback $feedback): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $feedback->assignee_id;
    }
}
