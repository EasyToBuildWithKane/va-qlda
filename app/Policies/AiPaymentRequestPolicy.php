<?php

namespace App\Policies;

use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;

class AiPaymentRequestPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function create(SystemAccount $account, AiPurchaseProposal $proposal): bool
    {
        // Chỉ tạo ĐNTT khi PĐX đã duyệt và chưa có ĐNTT
        return in_array($proposal->status, [
            AiPurchaseProposalStatus::Approved,
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ], true);
    }

    public function review(SystemAccount $account, AiPaymentRequest $pr): bool
    {
        return $account->role === SystemRole::Admin
            && $pr->status === AiPaymentRequestStatus::Pending;
    }

    public function markPaid(SystemAccount $account, AiPaymentRequest $pr): bool
    {
        return $account->role === SystemRole::Admin
            && $pr->status === AiPaymentRequestStatus::Approved;
    }

    public function delete(SystemAccount $account, AiPaymentRequest $pr): bool
    {
        return $account->role === SystemRole::Admin
            && $pr->status !== AiPaymentRequestStatus::Paid;
    }
}
