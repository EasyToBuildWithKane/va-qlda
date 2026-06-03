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

    public function viewPassword(SystemAccount $account): bool
    {
        return \App\Models\AiAccountPasswordViewer::canAccountViewPassword($account);
    }

    public function managePasswordViewers(SystemAccount $account): bool
    {
        return $account->role === SystemRole::Admin;
    }

    /** Admin hoặc người tạo phiếu đề xuất gắn với tài khoản. */
    public function updateStatus(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        $aiAccount->loadMissing('purchaseProposal');

        return $aiAccount->purchaseProposal?->created_by === $account->id;
    }
}
