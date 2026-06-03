<?php

namespace App\Services\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Support\Collection;

class AiPurchaseProposalPresenter
{
    public function __construct(
        private readonly AiAccountCostCalculator $costCalculator,
    ) {}

    /**
     * @param  Collection<int, AiPurchaseProposal>  $proposals
     * @return array<int, array<string, mixed>>
     */
    public function list(Collection $proposals): array
    {
        return $proposals
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (AiPurchaseProposal $p) => $this->row($p))
            ->all();
    }

    /**
     * @param  Collection<int, AiPurchaseProposal>  $proposals
     * @return array{pending: int, approved: int, rejected: int}
     */
    public function counts(Collection $proposals): array
    {
        return [
            'pending' => $proposals->where('status', AiPurchaseProposalStatus::Pending)->count(),
            'approved' => $proposals->where('status', AiPurchaseProposalStatus::Approved)->count(),
            'rejected' => $proposals->where('status', AiPurchaseProposalStatus::Rejected)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(AiPurchaseProposal $proposal): array
    {
        $proposal->loadMissing(['creator.employee', 'reviewer.employee']);
        $monthly = $this->costCalculator->monthlyAmount($proposal->cost_amount, $proposal->cost_unit);

        $creatorName = $proposal->creator?->employee?->full_name
            ?? $proposal->creator?->username
            ?? '—';

        $reviewerName = $proposal->reviewer?->employee?->full_name
            ?? $proposal->reviewer?->username;

        return [
            'id' => $proposal->id,
            'tool_name' => $proposal->tool_name,
            'group_function' => $proposal->group_function->value,
            'group_dot_color' => $proposal->group_function->dotColor(),
            'group_label' => $this->groupLabel($proposal->group_function),
            'license_type' => $proposal->license_type,
            'cost_amount' => $proposal->cost_amount,
            'cost_unit' => $proposal->cost_unit->value,
            'cost_monthly' => $monthly,
            'seats' => $proposal->seats,
            'justification' => $proposal->justification,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->labelVi(),
            'status_color' => $proposal->status->badgeColor(),
            'rejection_reason' => $proposal->rejection_reason,
            'review_notes' => $proposal->review_notes,
            'created_by_name' => $creatorName,
            'reviewed_by_name' => $reviewerName,
            'reviewed_at' => $proposal->reviewed_at?->format('Y-m-d H:i'),
            'created_at' => $proposal->created_at?->format('Y-m-d H:i'),
            'can_review' => $proposal->status === AiPurchaseProposalStatus::Pending,
            'can_edit_notes' => in_array($proposal->status, [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Rejected,
            ], true),
        ];
    }

    private function groupLabel(AiAccountGroupFunction $group): string
    {
        foreach (AiAccountGroupFunction::options() as $opt) {
            if ($opt['value'] === $group->value) {
                return $opt['label'];
            }
        }

        return $group->value;
    }
}
