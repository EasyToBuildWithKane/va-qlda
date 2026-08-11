<?php

namespace App\Policies;

use App\Models\AiAccount;
use App\Models\SystemAccount;

class AiAccountPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('ai_account.create');
    }

    public function update(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.update');
    }

    public function delete(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.delete');
    }

    public function renew(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.renew');
    }

    public function triggerReminder(SystemAccount $account): bool
    {
        return $account->allows('ai_account.trigger_reminder');
    }

    public function viewPassword(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.view_password');
    }

    public function updateStatus(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.update');
    }
}
