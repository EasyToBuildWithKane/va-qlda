<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Collection;

class AiAccountGrouper
{
    public function __construct(
        private readonly AiAccountCostCalculator $costCalculator,
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

            $monthlyTotal = $items->sum(fn (AiAccount $a) => $this->costCalculator->monthlyForAccount($a));

            $groups[] = [
                'group' => $key,
                'dot_color' => $groupEnum->dotColor(),
                'total' => $items->count(),
                'total_cost_monthly' => $monthlyTotal,
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
        $totalActiveMonthly = 0;
        $rows = [];

        foreach (AiAccountGroupFunction::ordered() as $groupEnum) {
            $items = $accounts->where('group_function', $groupEnum);
            if ($items->isEmpty()) {
                continue;
            }

            $active = $items->where('status', AiAccountStatus::Active);
            $monthly = $items->sum(fn (AiAccount $a) => $this->costCalculator->monthlyForAccount($a));
            $groupActiveMonthly = $active->sum(fn (AiAccount $a) => $this->costCalculator->monthlyForAccount($a));

            $rows[] = [
                'group' => $groupEnum->value,
                'dot_color' => $groupEnum->dotColor(),
                'total_accounts' => $items->count(),
                'active_accounts' => $active->count(),
                'expiring_soon' => $items->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $items->where('status', AiAccountStatus::Expired)->count(),
                'cancelled' => $items->where('status', AiAccountStatus::Cancelled)->count(),
                'cost_monthly' => $monthly,
                'cost_monthly_active' => $groupActiveMonthly,
            ];

            $totalActiveMonthly += $groupActiveMonthly;
        }

        $totalMonthly = array_sum(array_column($rows, 'cost_monthly'));
        if ($totalMonthly > 0) {
            foreach ($rows as &$row) {
                $row['cost_share_percent'] = (int) round(($row['cost_monthly'] / $totalMonthly) * 100);
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
        $allMonthly = $accounts->sum(fn (AiAccount $a) => $this->costCalculator->monthlyForAccount($a));

        return [
            'cards' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $totalActive,
                'expiring_soon' => $accounts->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $accounts->where('status', AiAccountStatus::Expired)->count(),
                'monthly_cost_active' => $totalActiveMonthly,
                'monthly_cost_all' => $allMonthly,
            ],
            'by_group' => $rows,
            'totals' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $totalActive,
                'cost_monthly' => $allMonthly,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(AiAccount $account, ?SystemAccount $viewer = null): array
    {
        $monthly = $this->costCalculator->monthlyAmount($account->cost_amount, $account->cost_unit);
        $daysLeft = $this->statusSync->daysUntilExpiry($account);
        $account->loadMissing('purchaseProposal');
        $canViewPassword = $viewer && $viewer->role === SystemRole::Admin;

        return [
            'id' => $account->id,
            'proposal_code' => $account->purchaseProposal?->proposal_code,
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
            'seats' => $account->seats,
            'status' => $account->status->value,
            'status_label' => $account->status->labelVi(),
            'status_color' => $account->status->badgeColor(),
            'days_until_expiry' => $daysLeft,
            'notify_before_days' => $account->notify_before_days,
            'notes' => $account->notes,
            'has_password' => $canViewPassword && filled($account->login_password),
            'password' => $canViewPassword ? $account->login_password : null,
            'can_renew' => in_array($account->status, [
                AiAccountStatus::ExpiringSoon,
                AiAccountStatus::Expired,
            ], true),
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
