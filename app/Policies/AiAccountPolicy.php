<?php

namespace App\Policies;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountPermission;

class AiAccountPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return (bool) $account->is_active;
    }

    public function view(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->isAdminTier()) {
            return true;
        }

        if ($aiAccount->created_by === $account->id) {
            return true;
        }

        return $aiAccount->grantFor($account) !== null;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('ai_account.create');
    }

    public function update(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.update')) {
            return true;
        }

        if ($aiAccount->created_by === $account->id) {
            return true;
        }

        return $aiAccount->hasPermission($account, AiAccountPermission::Edit);
    }

    public function delete(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.delete')) {
            return true;
        }

        if ($aiAccount->created_by === $account->id) {
            return true;
        }

        return $aiAccount->hasPermission($account, AiAccountPermission::Delete);
    }

    public function renew(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.renew')) {
            return true;
        }

        return $this->update($account, $aiAccount);
    }

    public function triggerReminder(SystemAccount $account): bool
    {
        return $account->allows('ai_account.trigger_reminder');
    }

    public function viewPassword(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.view_password')) {
            return true;
        }

        if ($aiAccount->created_by === $account->id) {
            return true;
        }

        return $aiAccount->hasPermission($account, AiAccountPermission::ViewPassword);
    }

    public function updateStatus(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $this->update($account, $aiAccount);
    }

    public function manageAccess(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.share') || $account->allows('ai_account.manage_access')) {
            return true;
        }

        if ($aiAccount->created_by === $account->id) {
            return true;
        }

        return $aiAccount->hasPermission($account, AiAccountPermission::Share);
    }
}
