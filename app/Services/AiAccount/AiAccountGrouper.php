<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Support\Collection;

class AiAccountGrouper
{
    public function __construct(
        private readonly AiAccountCostCalculator $costCalculator,
        private readonly AiAccountCountableProposalCost $countableProposalCost,
        private readonly AiAccountStatusSync $statusSync,
    ) {}

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array{groups: array<int, array<string, mixed>>, banner: array<string, mixed>|null}
     */
    public function grouped(Collection $accounts, ?string $search = null, ?SystemAccount $viewer = null): array
    {
        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $accounts = $accounts->filter(function (AiAccount $a) use ($q) {
                $hay = mb_strtolower(implode(' ', [
                    $a->tool_name,
                    $a->email_registered,
                    $a->license_type,
                    $a->notes ?? '',
                ]));

                return str_contains($hay, $q);
            })->values();
        }

        $byGroup = $accounts->groupBy(fn (AiAccount $a) => $a->group_function->value);
        $monthlyByGroup = $this->countableProposalCost->monthlyByGroup();
        $pendingByGroup = $this->countableProposalCost->pendingAccountMonthlyByGroup();

        $groups = [];
        foreach (AiAccountGroupFunction::ordered() as $groupEnum) {
            $key = $groupEnum->value;
            $items = $byGroup->get($key, collect());
            if ($items->isEmpty()) {
                continue;
            }

            $warningCount = $items->filter(fn (AiAccount $a) => in_array($a->status, [
                AiAccountStatus::ExpiringSoon,
                AiAccountStatus::Expired,
            ], true))->count();

            $monthlyTotal = $monthlyByGroup[$key] ?? 0;
            $pendingMonthly = $pendingByGroup[$key] ?? 0;

            $groups[] = [
                'group' => $key,
                'group_label' => $this->groupLabel($groupEnum),
                'dot_color' => $groupEnum->dotColor(),
                'total' => $items->count(),
                'total_cost_monthly' => $monthlyTotal,
                'proposal_monthly_pending_sync' => $pendingMonthly > 0 ? $pendingMonthly : null,
                'has_warning' => $warningCount > 0,
                'warning_count' => $warningCount,
                'default_expanded' => $warningCount > 0,
                'accounts' => $items
                    ->sortBy('tool_name')
                    ->values()
                    ->map(fn (AiAccount $a) => $this->accountPayload($a, $viewer))
                    ->all(),
            ];
        }

        return [
            'groups' => $groups,
            'banner' => $this->buildBanner($accounts),
        ];
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<string, mixed>
     */
    public function summary(Collection $accounts): array
    {
        $monthlyByGroup = $this->countableProposalCost->monthlyByGroup();
        $pendingByGroup = $this->countableProposalCost->pendingAccountMonthlyByGroup();
        $totalMonthly = $this->countableProposalCost->totalMonthly();

        $rows = [];
        foreach (AiAccountGroupFunction::ordered() as $groupEnum) {
            $key = $groupEnum->value;
            $items = $accounts->where('group_function', $groupEnum);
            $cost = $monthlyByGroup[$key] ?? 0;
            $pending = $pendingByGroup[$key] ?? 0;

            if ($items->isEmpty() && $cost === 0) {
                continue;
            }

            $active = $items->where('status', AiAccountStatus::Active);

            $rows[] = [
                'group' => $key,
                'dot_color' => $groupEnum->dotColor(),
                'total_accounts' => $items->count(),
                'active_accounts' => $active->count(),
                'expiring_soon' => $items->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $items->where('status', AiAccountStatus::Expired)->count(),
                'cancelled' => $items->where('status', AiAccountStatus::Cancelled)->count(),
                'cost_monthly' => $cost,
                'cost_monthly_active' => $cost,
                'proposal_monthly_pending_sync' => $pending > 0 ? $pending : null,
            ];
        }

        if ($totalMonthly > 0) {
            foreach ($rows as &$row) {
                $row['cost_share_percent'] = (int) round((($row['cost_monthly'] ?? 0) / $totalMonthly) * 100);
            }
            unset($row);
        } else {
            foreach ($rows as &$row) {
                $row['cost_share_percent'] = 0;
            }
            unset($row);
        }

        $totalAccounts = $accounts->count();
        $totalActive = $accounts->where('status', AiAccountStatus::Active)->count();

        $renewalDue = $accounts
            ->whereIn('status', [
                AiAccountStatus::ExpiringSoon,
                AiAccountStatus::Expired,
            ])
            ->filter(fn (AiAccount $a) => $this->countableProposalCost->accountHasCountableProposal($a));
        $renewalUnpaid = $renewalDue->filter(
            fn (AiAccount $a) => ($a->renewal_payment_status instanceof AiAccountRenewalPaymentStatus
                ? $a->renewal_payment_status
                : AiAccountRenewalPaymentStatus::tryFrom((string) $a->renewal_payment_status))
                === AiAccountRenewalPaymentStatus::Unpaid,
        );
        $renewalPaid = $renewalDue->filter(
            fn (AiAccount $a) => ($a->renewal_payment_status instanceof AiAccountRenewalPaymentStatus
                ? $a->renewal_payment_status
                : AiAccountRenewalPaymentStatus::tryFrom((string) $a->renewal_payment_status))
                === AiAccountRenewalPaymentStatus::Paid,
        );

        return [
            'cards' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $totalActive,
                'expiring_soon' => $accounts->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $accounts->where('status', AiAccountStatus::Expired)->count(),
                'monthly_cost_active' => $totalMonthly,
                'monthly_cost_all' => $totalMonthly,
                'monthly_cost_running' => $totalMonthly,
                'renewal_due_count' => $renewalDue->count(),
                'renewal_unpaid_count' => $renewalUnpaid->count(),
                'renewal_paid_count' => $renewalPaid->count(),
                'monthly_cost_unpaid_renewal' => $renewalUnpaid->sum(
                    fn (AiAccount $a) => $this->countableProposalCost->monthlyForAccountInBudget($a),
                ),
            ],
            'by_group' => $rows,
            'totals' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $totalActive,
                'cost_monthly' => $totalMonthly,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountPayload(AiAccount $account, ?SystemAccount $viewer = null): array
    {
        $monthly = $this->costCalculator->monthlyAmount($account->cost_amount, $account->cost_unit);
        $daysLeft = $this->statusSync->daysUntilExpiry($account);
        $daysLeftSigned = $this->statusSync->daysUntilExpirySigned($account);
        $account->loadMissing('purchaseProposal');
        $canViewPassword = $viewer !== null && $viewer->can('viewPassword', $account);
        $urgency = match ($account->status) {
            AiAccountStatus::Expired => 'expired',
            AiAccountStatus::ExpiringSoon => 'expiring_soon',
            default => null,
        };

        $proposal = $account->purchaseProposal;
        $paymentStatus = $account->renewal_payment_status instanceof AiAccountRenewalPaymentStatus
            ? $account->renewal_payment_status
            : AiAccountRenewalPaymentStatus::tryFrom((string) $account->renewal_payment_status)
                ?? AiAccountRenewalPaymentStatus::Unpaid;
        $hasCountableProposal = $this->countableProposalCost->accountHasCountableProposal($account);
        $showRenewalPayment = $hasCountableProposal && in_array($account->status, [
            AiAccountStatus::ExpiringSoon,
            AiAccountStatus::Expired,
        ], true);
        $budgetMonthly = $hasCountableProposal
            ? $this->countableProposalCost->monthlyForAccountInBudget($account)
            : 0;

        return [
            'id' => $account->id,
            'purchase_proposal_id' => $proposal?->id,
            'proposal_code' => $proposal?->proposal_code,
            'proposal_url' => $proposal
                ? route('ai-accounts.cost-report', ['proposal' => $proposal->id])
                : null,
            'tool_name' => $account->tool_name,
            'license_type' => $account->license_type,
            'license_key' => $account->license_key,
            'group_function' => $account->group_function->value,
            'group_label' => $this->groupLabel($account->group_function),
            'email_registered' => $account->email_registered,
            'purchase_date' => $account->purchase_date->format('Y-m-d'),
            'expiry_date' => $account->expiry_date->format('Y-m-d'),
            'cost_amount' => $account->cost_amount,
            'cost_unit' => $account->cost_unit->value,
            'cost_monthly' => $monthly,
            'budget_cost_monthly' => $budgetMonthly,
            'seats' => $account->seats,
            'status' => $account->status->value,
            'status_label' => $account->status->labelVi(),
            'status_color' => $account->status->badgeColor(),
            'days_until_expiry' => $daysLeft,
            'days_until_expiry_signed' => $daysLeftSigned,
            'urgency' => $urgency,
            'last_reminded_at' => $account->last_reminded_at?->format('d/m/Y H:i'),
            'notify_before_days' => $account->notify_before_days,
            'notes' => $account->notes,
            'can_view_password' => $canViewPassword,
            'has_password' => $canViewPassword && filled($account->login_password),
            'password' => $canViewPassword ? $account->login_password : null,
            'can_renew' => in_array($account->status, [
                AiAccountStatus::ExpiringSoon,
                AiAccountStatus::Expired,
            ], true),
            'can_update_status' => $viewer?->can('updateStatus', $account) ?? false,
            'status_locked' => $account->status_locked_at !== null,
            'renewal_payment_status' => $paymentStatus->value,
            'renewal_payment_status_label' => $paymentStatus->labelVi(),
            'renewal_payment_status_color' => $paymentStatus->badgeColor(),
            'renewal_paid_at' => $account->renewal_paid_at?->format('d/m/Y H:i'),
            'show_renewal_payment' => $showRenewalPayment,
            'can_update_renewal_payment' => $showRenewalPayment
                && ($viewer?->can('updateRenewalPayment', $account) ?? false),
            'cost_in_budget' => $hasCountableProposal,
        ];
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<string, mixed>|null
     */
    private function groupLabel(AiAccountGroupFunction $group): string
    {
        foreach (AiAccountGroupFunction::options() as $opt) {
            if ($opt['value'] === $group->value) {
                return $opt['label'];
            }
        }

        return $group->value;
    }

    private function buildBanner(Collection $accounts): ?array
    {
        $warn = $accounts->filter(fn (AiAccount $a) => in_array($a->status, [
            AiAccountStatus::ExpiringSoon,
            AiAccountStatus::Expired,
        ], true));

        if ($warn->isEmpty()) {
            return null;
        }

        $items = $warn->take(5)->map(fn (AiAccount $a) => [
            'tool_name' => $a->tool_name,
            'group' => $a->group_function->value,
            'status' => $a->status->value,
        ])->values()->all();

        $expiring = $warn->where('status', AiAccountStatus::ExpiringSoon)->count();
        $expired = $warn->where('status', AiAccountStatus::Expired)->count();

        return [
            'total' => $warn->count(),
            'expiring_soon_count' => $expiring,
            'expired_count' => $expired,
            'items' => $items,
        ];
    }
}
