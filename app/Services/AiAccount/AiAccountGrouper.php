<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\SecurityAuditLogger;
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
                'group_label' => $this->groupLabel($groupEnum),
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
        $rows = [];
        $totalMonthly = 0;

        foreach (AiAccountGroupFunction::ordered() as $groupEnum) {
            $items = $accounts->where('group_function', $groupEnum);
            if ($items->isEmpty()) {
                continue;
            }

            $cost = $items->sum(fn (AiAccount $a) => $this->costCalculator->monthlyForAccount($a));
            $totalMonthly += $cost;
            $active = $items->where('status', AiAccountStatus::Active);

            $rows[] = [
                'group' => $groupEnum->value,
                'group_label' => $this->groupLabel($groupEnum),
                'dot_color' => $groupEnum->dotColor(),
                'total_accounts' => $items->count(),
                'active_accounts' => $active->count(),
                'expiring_soon' => $items->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $items->where('status', AiAccountStatus::Expired)->count(),
                'cancelled' => $items->where('status', AiAccountStatus::Cancelled)->count(),
                'cost_monthly' => $cost,
                'cost_monthly_active' => $cost,
            ];
        }

        foreach ($rows as &$row) {
            $row['cost_share_percent'] = $totalMonthly > 0
                ? (int) round(((int) $row['cost_monthly'] / $totalMonthly) * 100)
                : 0;
        }
        unset($row);

        $totalAccounts = $accounts->count();
        $totalActive = $accounts->where('status', AiAccountStatus::Active)->count();

        return [
            'cards' => [
                'total_accounts' => $totalAccounts,
                'active_accounts' => $totalActive,
                'expiring_soon' => $accounts->where('status', AiAccountStatus::ExpiringSoon)->count(),
                'expired' => $accounts->where('status', AiAccountStatus::Expired)->count(),
                'monthly_cost_active' => $totalMonthly,
                'monthly_cost_all' => $totalMonthly,
                'monthly_cost_running' => $totalMonthly,
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
        $canViewPassword = $viewer !== null && $viewer->can('viewPassword', $account);
        $urgency = match ($account->status) {
            AiAccountStatus::Expired => 'expired',
            AiAccountStatus::ExpiringSoon => 'expiring_soon',
            default => null,
        };

        if ($viewer && $canViewPassword && filled($account->login_password)) {
            SecurityAuditLogger::aiAccountPasswordViewed($viewer, $account->id, $account->tool_name);
        }

        return [
            'id' => $account->id,
            'tool_name' => $account->tool_name,
            'group_function' => $account->group_function->value,
            'group_label' => $this->groupLabel($account->group_function),
            'email_registered' => $account->email_registered,
            'purchase_date' => $account->purchase_date->format('Y-m-d'),
            'expiry_date' => $account->expiry_date->format('Y-m-d'),
            'proposal_sent_at' => $account->proposal_sent_at?->format('Y-m-d'),
            'payment_request_sent_at' => $account->payment_request_sent_at?->format('Y-m-d'),
            'proposal_documents' => $account->documentsFor('proposal'),
            'payment_request_documents' => $account->documentsFor('payment_request'),
            'cost_amount' => $account->cost_amount,
            'cost_unit' => $account->cost_unit->value,
            'cost_monthly' => $monthly,
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

        return [
            'total' => $warn->count(),
            'expiring_soon_count' => $warn->where('status', AiAccountStatus::ExpiringSoon)->count(),
            'expired_count' => $warn->where('status', AiAccountStatus::Expired)->count(),
            'items' => $items,
        ];
    }
}
