<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\SystemAccount;

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
        return $account->allows('feedback.create');
    }

    public function update(SystemAccount $account, Feedback $feedback): bool
    {
        return $account->allows('feedback.update') || $this->isAssignee($account, $feedback);
    }

    public function delete(SystemAccount $account, Feedback $feedback): bool
    {
        return $account->allows('feedback.delete');
    }

    private function isAssignee(SystemAccount $account, Feedback $feedback): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $feedback->assignee_id;
    }
}
