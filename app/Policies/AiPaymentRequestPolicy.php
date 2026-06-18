<?php

namespace App\Policies;

use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;

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
        return $account->allows('ai_proposal.payment_review')
            && $pr->status === AiPaymentRequestStatus::Pending;
    }

    public function markPaid(SystemAccount $account, AiPaymentRequest $pr): bool
    {
        return $account->allows('ai_proposal.payment_mark_paid')
            && $pr->status === AiPaymentRequestStatus::Approved;
    }

    public function delete(SystemAccount $account, AiPaymentRequest $pr): bool
    {
        return $account->allows('ai_proposal.payment_delete')
            && $pr->status !== AiPaymentRequestStatus::Paid;
    }
}
