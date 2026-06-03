<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Support\Collection;

/**
 * Tổng hợp chi phí theo nhóm: tài khoản AI + phiếu đã duyệt chưa gắn tài khoản.
 */
class AiAccountCostSummaryBuilder
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
        private readonly AiAccountCostCalculator $costCalculator,
    ) {}

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<string, mixed>
     */
    public function build(Collection $accounts): array
    {
        $summary = $this->grouper->summary($accounts);
        $this->mergeApprovedProposalCosts($summary);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function mergeApprovedProposalCosts(array &$summary): void
    {
        $proposals = AiPurchaseProposal::query()
            ->whereNull('ai_account_id')
            ->whereIn('status', [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Purchased,
                AiPurchaseProposalStatus::Active,
            ])
            ->get();

        if ($proposals->isEmpty()) {
            return;
        }

        $extraByGroup = [];
        foreach ($proposals as $proposal) {
            $key = $proposal->group_function->value;
            $monthly = $this->costCalculator->monthlyAmount(
                $proposal->cost_amount,
                $proposal->cost_unit,
            );
            $extraByGroup[$key] = ($extraByGroup[$key] ?? 0) + $monthly;
        }

        $rows = $summary['by_group'] ?? [];
        $indexed = collect($rows)->keyBy('group');

        foreach ($extraByGroup as $group => $extraMonthly) {
            if ($indexed->has($group)) {
                $row = $indexed->get($group);
                $row['cost_monthly'] = ($row['cost_monthly'] ?? 0) + $extraMonthly;
                $row['cost_monthly_active'] = ($row['cost_monthly_active'] ?? 0) + $extraMonthly;
                $row['proposal_monthly_pending_sync'] = $extraMonthly;
                $indexed->put($group, $row);
            } else {
                $enum = AiAccountGroupFunction::from($group);
                $indexed->put($group, [
                    'group' => $group,
                    'dot_color' => $enum->dotColor(),
                    'total_accounts' => 0,
                    'active_accounts' => 0,
                    'expiring_soon' => 0,
                    'expired' => 0,
                    'cancelled' => 0,
                    'cost_monthly' => $extraMonthly,
                    'cost_monthly_active' => $extraMonthly,
                    'proposal_monthly_pending_sync' => $extraMonthly,
                    'cost_share_percent' => 0,
                ]);
            }
        }

        $rows = [];
        foreach (AiAccountGroupFunction::ordered() as $groupEnum) {
            if ($indexed->has($groupEnum->value)) {
                $rows[] = $indexed->get($groupEnum->value);
            }
        }
        $totalMonthly = array_sum(array_column($rows, 'cost_monthly'));
        if ($totalMonthly > 0) {
            foreach ($rows as &$row) {
                $row['cost_share_percent'] = (int) round(($row['cost_monthly'] / $totalMonthly) * 100);
            }
            unset($row);
        }

        $summary['by_group'] = $rows;
        $summary['cards']['monthly_cost_all'] = ($summary['cards']['monthly_cost_all'] ?? 0)
            + array_sum($extraByGroup);
        $summary['cards']['monthly_cost_active'] = ($summary['cards']['monthly_cost_active'] ?? 0)
            + array_sum($extraByGroup);
        $summary['totals']['cost_monthly'] = $summary['cards']['monthly_cost_all'];
    }
}
