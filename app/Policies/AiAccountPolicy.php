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

    public function updateRenewalPayment(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.update_renewal_payment');
    }

    public function viewPassword(SystemAccount $account, AiAccount $aiAccount): bool
    {
        return $account->allows('ai_account.view_password')
            || \App\Models\AiAccountPasswordViewer::canViewPassword($account, $aiAccount);
    }

    public function managePasswordViewers(SystemAccount $account): bool
    {
        return $account->allows('ai_account.manage_password_viewers');
    }

    /** Quyền cập nhật trạng thái, hoặc người tạo phiếu đề xuất gắn với tài khoản. */
    public function updateStatus(SystemAccount $account, AiAccount $aiAccount): bool
    {
        if ($account->allows('ai_account.update')) {
            return true;
        }

        $aiAccount->loadMissing('purchaseProposal');

        return $aiAccount->purchaseProposal?->created_by === $account->id;
    }
}
