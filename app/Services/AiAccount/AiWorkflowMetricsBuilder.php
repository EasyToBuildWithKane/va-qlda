<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPaymentRequestStatus;

/**
 * Tổng hợp chỉ số workflow PĐX → ĐNTT → Tài khoản AI.
 *
 * Mỗi giai đoạn có số liệu riêng biệt — không gộp "đã duyệt = đã chi" hay "đã mua = đã cấp phát".
 */
class AiWorkflowMetricsBuilder
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            // — PĐX —
            'budget_proposed_total' => $this->proposalSum(['pending', 'submitted', 'approved', 'purchased', 'active']),
            'budget_proposal_approved_total' => $this->proposalSum(['approved', 'purchased', 'active']),

            // — ĐNTT —
            'budget_payment_request_total' => $this->paymentRequestSum(AiPaymentRequestStatus::values()),
            'budget_payment_approved_total' => $this->paymentRequestSum([
                AiPaymentRequestStatus::Approved->value,
                AiPaymentRequestStatus::Paid->value,
            ]),
            'budget_paid_total' => $this->paymentRequestSum([AiPaymentRequestStatus::Paid->value]),

            // — Tài khoản —
            'actual_purchase_total' => $this->actualPurchaseTotal(),
            'accounts_allocated_count' => $this->accountsInLifecycle([
                AiAccountLifecycleStatus::Allocated->value,
                AiAccountLifecycleStatus::InUse->value,
            ]),
            'accounts_expiring_soon_count' => AiAccount::query()
                ->withCountablePurchaseProposal()
                ->where('status', AiAccountStatus::ExpiringSoon->value)
                ->count(),
            'accounts_expired_count' => AiAccount::query()
                ->withCountablePurchaseProposal()
                ->where('status', AiAccountStatus::Expired->value)
                ->count(),
        ];
    }

    /** @param list<string> $statuses */
    private function proposalSum(array $statuses): int
    {
        return (int) AiPurchaseProposal::query()
            ->whereIn('status', $statuses)
            ->sum('cost_amount');
    }

    /** @param list<string> $statuses */
    private function paymentRequestSum(array $statuses): int
    {
        return (int) AiPaymentRequest::query()
            ->whereIn('status', $statuses)
            ->sum('amount');
    }

    private function actualPurchaseTotal(): int
    {
        return (int) AiAccount::query()
            ->withCountablePurchaseProposal()
            ->whereNotNull('actual_purchase_cost')
            ->sum('actual_purchase_cost');
    }

    /** @param list<string> $statuses */
    private function accountsInLifecycle(array $statuses): int
    {
        return AiAccount::query()
            ->withCountablePurchaseProposal()
            ->whereIn('lifecycle_status', $statuses)
            ->count();
    }
}
