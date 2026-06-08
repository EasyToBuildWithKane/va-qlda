<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Support\Collection;

/**
 * Chi phí ngân sách chỉ tính từ phiếu đề xuất đã duyệt (và các trạng thái sau duyệt).
 */
class AiAccountCountableProposalCost
{
    public function __construct(
        private readonly AiAccountCostCalculator $costCalculator,
    ) {}

    /** @return list<AiPurchaseProposalStatus> */
    public static function countableStatuses(): array
    {
        return [
            AiPurchaseProposalStatus::Approved,
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ];
    }

    /** @return Collection<int, AiPurchaseProposal> */
    public function countableProposals(): Collection
    {
        return AiPurchaseProposal::query()
            ->whereIn('status', array_map(
                fn (AiPurchaseProposalStatus $s) => $s->value,
                self::countableStatuses(),
            ))
            ->where(function ($query) {
                $query->whereNull('ai_account_id')
                    ->orWhereHas('aiAccount');
            })
            ->get();
    }

    public function monthlyForProposal(AiPurchaseProposal $proposal): int
    {
        return $this->costCalculator->monthlyAmount($proposal->cost_amount, $proposal->cost_unit);
    }

    public function accountHasCountableProposal(AiAccount $account): bool
    {
        $account->loadMissing('purchaseProposal');
        $proposal = $account->purchaseProposal;

        return $proposal !== null
            && in_array($proposal->status, self::countableStatuses(), true);
    }

    public function monthlyForAccountInBudget(AiAccount $account): int
    {
        $account->loadMissing('purchaseProposal');
        $proposal = $account->purchaseProposal;
        if ($proposal === null || ! in_array($proposal->status, self::countableStatuses(), true)) {
            return 0;
        }

        return $this->monthlyForProposal($proposal);
    }

    public function totalMonthly(): int
    {
        return $this->countableProposals()->sum(fn (AiPurchaseProposal $p) => $this->monthlyForProposal($p));
    }

    /**
     * @return array<string, int>
     */
    public function monthlyByGroup(): array
    {
        $byGroup = [];
        foreach ($this->countableProposals() as $proposal) {
            $key = $proposal->group_function->value;
            $byGroup[$key] = ($byGroup[$key] ?? 0) + $this->monthlyForProposal($proposal);
        }

        return $byGroup;
    }

    /**
     * Chi phí phiếu đã duyệt nhưng chưa lập tài khoản AI.
     *
     * @return array<string, int>
     */
    public function pendingAccountMonthlyByGroup(): array
    {
        $byGroup = [];
        foreach ($this->countableProposals()->filter(fn (AiPurchaseProposal $p) => $p->hasRemainingAccountSlots()) as $proposal) {
            $key = $proposal->group_function->value;
            $byGroup[$key] = ($byGroup[$key] ?? 0) + $this->monthlyForProposal($proposal);
        }

        return $byGroup;
    }
}
