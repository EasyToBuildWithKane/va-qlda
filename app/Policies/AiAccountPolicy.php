<?php

namespace App\Policies;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

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
        return true;
    }

    public function update(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return true;
    }

    public function delete(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return true;
    }

    public function renew(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return true;
    }

    public function triggerReminder(SystemAccount $account): bool
    {
        return $account->role === SystemRole::Admin;
    }
}
