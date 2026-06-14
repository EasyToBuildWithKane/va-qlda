<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPaymentRequestStatus;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * KPI, biểu đồ và báo cáo chi tiết AI — nguồn chi phí ngân sách theo PĐX countable (xem AI_ACCOUNTS.md).
 */
class AiExecutiveAnalyticsBuilder
{
    private const INACTIVE_DAYS_DEFAULT = 30;

    public function __construct(
        private readonly AiAccountCountableProposalCost $countableProposalCost,
        private readonly AiAccountCostCalculator $costCalculator,
        private readonly AiAccountStatusSync $statusSync,
        private readonly AiWorkflowMetricsBuilder $workflowMetrics,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function buildDashboard(array $options = []): array
    {
        AiAccount::purgeOrphanedFromProposal();

        $granularity = in_array($options['granularity'] ?? 'month', ['day', 'month', 'quarter', 'year'], true)
            ? (string) $options['granularity']
            : 'month';
        $comparePreviousYear = (bool) ($options['compare_previous_year'] ?? true);
        $inactiveDays = max(7, (int) ($options['inactive_days'] ?? self::INACTIVE_DAYS_DEFAULT));

        $accounts = $this->registeredAccounts();
        $metrics = $this->workflowMetrics->build();
        $totalMonthly = $this->countableProposalCost->totalMonthly();
        $now = now();

        $uniqueUsers = $accounts
            ->pluck('allocated_to_name')
            ->merge(
                $accounts->map(fn (AiAccount $a) => $a->email_registered)->filter(),
            )
            ->filter()
            ->unique()
            ->count();

        $uniqueDepartments = $this->departmentKeysFromAccounts($accounts)->count();
        $avgCostPerUser = $uniqueUsers > 0 ? (int) round($totalMonthly / $uniqueUsers) : 0;
        $avgCostPerDept = $uniqueDepartments > 0 ? (int) round($totalMonthly / $uniqueDepartments) : 0;

        $byTool = $this->aggregateByTool($accounts);
        $byDepartment = $this->aggregateByDepartment($accounts);

        $usageRate = $this->usageRatePercent($accounts);
        $accountsByType = collect($byTool)->mapWithKeys(fn (array $row) => [$row['tool_name'] => $row['account_count']])->all();

        $budgetApproved = (int) ($metrics['budget_proposal_approved_total'] ?? 0);
        $budgetPaid = (int) ($metrics['budget_paid_total'] ?? 0);
        $budgetUsed = (int) ($metrics['actual_purchase_total'] ?? 0);
        $budgetCommitted = max(0, (int) ($metrics['budget_payment_approved_total'] ?? 0) - $budgetPaid);
        $budgetRemaining = max(0, $budgetApproved - $budgetUsed - $budgetCommitted);

        $statusDonut = $this->accountStatusDonut($accounts);
        $budgetDonut = [
            ['key' => 'used', 'label' => 'Đã sử dụng', 'amount' => $budgetUsed],
            ['key' => 'committed', 'label' => 'Đã cam kết', 'amount' => $budgetCommitted],
            ['key' => 'remaining', 'label' => 'Chưa sử dụng', 'amount' => $budgetRemaining],
        ];

        $yearStart = $now->copy()->startOfYear();
        $yearPaid = $this->sumPaidBetween($yearStart, $now);
        $monthPaid = $this->sumPaidBetween($now->copy()->startOfMonth(), $now);

        $kpis = [
            'accounts_in_use' => $accounts->where('status', AiAccountStatus::Active)->count(),
            'accounts_expiring_soon' => (int) ($metrics['accounts_expiring_soon_count'] ?? 0),
            'accounts_expired' => (int) ($metrics['accounts_expired_count'] ?? 0),
            'cost_current_month' => $monthPaid > 0 ? $monthPaid : $totalMonthly,
            'cost_current_year' => $yearPaid > 0 ? $yearPaid : $totalMonthly * max(1, $now->month),
            'avg_cost_per_user' => $avgCostPerUser,
            'avg_cost_per_department' => $avgCostPerDept,
            'accounts_by_type' => $accountsByType,
            'usage_rate_percent' => $usageRate,
            'budget_approved_total' => $budgetApproved,
            'budget_paid_total' => $budgetPaid,
            'budget_used_total' => $budgetUsed,
            'monthly_run_rate' => $totalMonthly,
        ];

        return [
            'generated_at' => $now->toIso8601String(),
            'kpis' => $kpis,
            'cost_over_time' => $this->costOverTimeSeries(
                $granularity,
                $comparePreviousYear,
                $totalMonthly,
            ),
            'by_product' => $byTool,
            'by_department' => $byDepartment,
            'budget_allocation' => $budgetDonut,
            'account_status' => $statusDonut,
            'top' => $this->topTen($accounts),
            'alerts' => $this->buildAlerts($accounts, $inactiveDays, $totalMonthly),
            'workflow_metrics' => $metrics,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, stats: array<string, mixed>, meta: array<string, mixed>}
     */
    public function buildReport(array $filters = []): array
    {
        AiAccount::purgeOrphanedFromProposal();

        $query = AiAccount::query()
            ->visibleInRegistry()
            ->with(['purchaseProposal.creator.employee', 'purchaseProposal.reviewer.employee']);

        $this->applyReportFilters($query, $filters);

        /** @var Collection<int, AiAccount> $accounts */
        $accounts = $query->orderBy('tool_name')->get();
        foreach ($accounts as $account) {
            $this->statusSync->syncAndSave($account);
        }

        $rows = $accounts
            ->filter(fn (AiAccount $a) => $this->countableProposalCost->accountHasCountableProposal($a)
                || $a->purchaseProposal === null)
            ->map(fn (AiAccount $a) => $this->reportRow($a))
            ->values()
            ->all();

        if (! empty($filters['cost_min']) || ! empty($filters['cost_max'])) {
            $min = isset($filters['cost_min']) ? (int) $filters['cost_min'] : null;
            $max = isset($filters['cost_max']) ? (int) $filters['cost_max'] : null;
            $rows = array_values(array_filter($rows, function (array $row) use ($min, $max) {
                $m = (int) ($row['cost_monthly'] ?? 0);
                if ($min !== null && $m < $min) {
                    return false;
                }
                if ($max !== null && $m > $max) {
                    return false;
                }

                return true;
            }));
        }

        return [
            'rows' => $rows,
            'stats' => $this->reportFooterStats($rows),
            'meta' => [
                'total' => count($rows),
                'filters_applied' => array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== 'all'),
            ],
        ];
    }

    /** @return Collection<int, AiAccount> */
    private function registeredAccounts(): Collection
    {
        $accounts = AiAccount::query()
            ->visibleInRegistry()
            ->with('purchaseProposal')
            ->get();

        foreach ($accounts as $account) {
            $this->statusSync->syncAndSave($account);
        }

        return $accounts->filter(
            fn (AiAccount $a) => $this->countableProposalCost->accountHasCountableProposal($a),
        )->values();
    }

    /** @param  Collection<int, AiAccount>  $accounts */
    private function usageRatePercent(Collection $accounts): float
    {
        if ($accounts->isEmpty()) {
            return 0.0;
        }
        $inUse = $accounts->filter(fn (AiAccount $a) => ($a->lifecycle_status ?? AiAccountLifecycleStatus::InUse) === AiAccountLifecycleStatus::InUse
            || $a->status === AiAccountStatus::Active)->count();

        return round(($inUse / $accounts->count()) * 100, 1);
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByTool(Collection $accounts): array
    {
        $groups = [];
        foreach ($accounts as $account) {
            $tool = trim($account->tool_name) ?: 'Khác';
            $key = mb_strtolower($tool);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'tool_name' => $tool,
                    'account_count' => 0,
                    'cost_monthly' => 0,
                    'cost_total_sample' => 0,
                ];
            }
            $monthly = $this->countableProposalCost->monthlyForAccountInBudget($account);
            $groups[$key]['account_count']++;
            $groups[$key]['cost_monthly'] += $monthly;
        }

        $rows = array_values($groups);
        foreach ($rows as &$row) {
            $row['avg_cost_monthly'] = $row['account_count'] > 0
                ? (int) round($row['cost_monthly'] / $row['account_count'])
                : 0;
        }
        unset($row);

        usort($rows, fn ($a, $b) => $b['cost_monthly'] <=> $a['cost_monthly']);

        return $rows;
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByDepartment(Collection $accounts): array
    {
        $groups = [];
        $totalMonthly = 0;
        foreach ($accounts as $account) {
            $dept = $this->departmentLabelForAccount($account);
            $monthly = $this->countableProposalCost->monthlyForAccountInBudget($account);
            $totalMonthly += $monthly;
            if (! isset($groups[$dept])) {
                $groups[$dept] = [
                    'department' => $dept,
                    'account_count' => 0,
                    'cost_monthly' => 0,
                ];
            }
            $groups[$dept]['account_count']++;
            $groups[$dept]['cost_monthly'] += $monthly;
        }

        $rows = array_values($groups);
        foreach ($rows as &$row) {
            $row['share_percent'] = $totalMonthly > 0
                ? (int) round(($row['cost_monthly'] / $totalMonthly) * 100)
                : 0;
        }
        unset($row);

        usort($rows, fn ($a, $b) => $b['cost_monthly'] <=> $a['cost_monthly']);

        return $rows;
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function accountStatusDonut(Collection $accounts): array
    {
        $awaiting = AiPurchaseProposal::query()
            ->whereIn('status', AiAccount::countableProposalStatusValues())
            ->whereNull('ai_account_id')
            ->count();

        return [
            ['key' => 'active', 'label' => 'Đang sử dụng', 'count' => $accounts->where('status', AiAccountStatus::Active)->count()],
            ['key' => 'expiring_soon', 'label' => 'Sắp hết hạn', 'count' => $accounts->where('status', AiAccountStatus::ExpiringSoon)->count()],
            ['key' => 'expired', 'label' => 'Hết hạn', 'count' => $accounts->where('status', AiAccountStatus::Expired)->count()],
            ['key' => 'unallocated', 'label' => 'Chưa cấp phát', 'count' => $awaiting + $accounts->filter(
                fn (AiAccount $a) => in_array($a->lifecycle_status, [
                    AiAccountLifecycleStatus::NotPurchased,
                    AiAccountLifecycleStatus::Purchased,
                ], true) && $a->allocated_at === null,
            )->count()],
        ];
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<string, mixed>
     */
    private function topTen(Collection $accounts): array
    {
        $byTool = $this->aggregateByTool($accounts);

        $byUser = [];
        foreach ($accounts as $account) {
            $user = $account->allocated_to_name ?: $account->email_registered ?: 'Chưa gán';
            $byUser[$user] = ($byUser[$user] ?? 0) + 1;
        }
        arsort($byUser);

        $expiring = $accounts
            ->filter(fn (AiAccount $a) => in_array($a->status, [AiAccountStatus::ExpiringSoon, AiAccountStatus::Active], true))
            ->sortBy(fn (AiAccount $a) => $a->expiry_date)
            ->take(10)
            ->map(fn (AiAccount $a) => [
                'id' => $a->id,
                'tool_name' => $a->tool_name,
                'allocated_to_name' => $a->allocated_to_name,
                'expiry_date' => $a->expiry_date->format('Y-m-d'),
                'days_until_expiry' => $this->statusSync->daysUntilExpiry($a),
                'status' => $a->status->value,
                'status_label' => $a->status->labelVi(),
            ])
            ->values()
            ->all();

        return [
            'costly_products' => array_slice($byTool, 0, 10),
            'users_most_accounts' => collect($byUser)->take(10)->map(fn ($count, $name) => [
                'user_name' => $name,
                'account_count' => $count,
            ])->values()->all(),
            'expiring_soon' => $expiring,
        ];
    }

    /**
     * @param  Collection<int, AiAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(Collection $accounts, int $inactiveDays, int $totalMonthly): array
    {
        $alerts = [];
        $expiringSoon = $accounts->where('status', AiAccountStatus::ExpiringSoon);
        if ($expiringSoon->isNotEmpty()) {
            $alerts[] = [
                'code' => 'expiring_soon',
                'level' => 'warning',
                'title' => 'Tài khoản sắp hết hạn',
                'message' => "{$expiringSoon->count()} tài khoản hết hạn trong 30 ngày tới.",
                'count' => $expiringSoon->count(),
            ];
        }

        $awaiting = AiPurchaseProposal::query()
            ->whereIn('status', AiAccount::countableProposalStatusValues())
            ->whereNull('ai_account_id')
            ->count();
        if ($awaiting > 0) {
            $alerts[] = [
                'code' => 'awaiting_allocation',
                'level' => 'warning',
                'title' => 'Tài khoản chưa cấp phát',
                'message' => "{$awaiting} phiếu đã duyệt chưa lập tài khoản AI.",
                'count' => $awaiting,
            ];
        }

        $metrics = $this->workflowMetrics->build();
        $approved = (int) ($metrics['budget_proposal_approved_total'] ?? 0);
        $paid = (int) ($metrics['budget_paid_total'] ?? 0);
        if ($approved > 0 && $paid > $approved) {
            $alerts[] = [
                'code' => 'over_budget',
                'level' => 'error',
                'title' => 'Chi phí vượt ngân sách',
                'message' => 'Thanh toán đã vượt tổng ngân sách PĐX đã duyệt.',
                'count' => 1,
            ];
        }

        $cutoff = now()->subDays($inactiveDays);
        $inactive = $accounts->filter(function (AiAccount $a) use ($cutoff) {
            if ($a->status !== AiAccountStatus::Active) {
                return false;
            }
            $last = $a->last_reminded_at ?? $a->allocated_at ?? $a->purchase_date;

            return $last !== null && Carbon::parse($last)->lt($cutoff);
        });
        if ($inactive->isNotEmpty()) {
            $alerts[] = [
                'code' => 'inactive_accounts',
                'level' => 'info',
                'title' => "Tài khoản không sử dụng ({$inactiveDays} ngày)",
                'message' => "{$inactive->count()} tài khoản không có hoạt động ghi nhận gần đây.",
                'count' => $inactive->count(),
            ];
        }

        $thisMonth = $this->sumPaidBetween(now()->startOfMonth(), now());
        $lastMonth = $this->sumPaidBetween(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());
        if ($lastMonth > 0 && $thisMonth > $lastMonth * 1.25) {
            $pct = (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100);
            $alerts[] = [
                'code' => 'cost_spike',
                'level' => 'error',
                'title' => 'Chi phí tăng bất thường',
                'message' => "Chi phí tháng này cao hơn tháng trước khoảng {$pct}%.",
                'count' => 1,
            ];
        }

        return $alerts;
    }

    /** @return array<string, mixed> */
    private function costOverTimeSeries(string $granularity, bool $comparePreviousYear, int $monthlyRunRate): array
    {
        $now = now();
        $start = match ($granularity) {
            'day' => $now->copy()->subDays(29)->startOfDay(),
            'month' => $now->copy()->subMonths(11)->startOfMonth(),
            'quarter' => $now->copy()->subQuarters(3)->startOfQuarter(),
            'year' => $now->copy()->subYears(4)->startOfYear(),
            default => $now->copy()->subMonths(11)->startOfMonth(),
        };

        $period = $this->buildPeriod($start, $now, $granularity);
        $labels = [];
        $actual = [];
        $budget = [];
        $previousYear = [];

        foreach ($period as $point) {
            if (! $point instanceof Carbon) {
                continue;
            }
            [$rangeStart, $rangeEnd, $label] = $this->bucketRange($point, $granularity);
            $labels[] = $label;
            $actual[] = $this->sumPaidBetween($rangeStart, $rangeEnd);
            $budget[] = $this->budgetForBucket($rangeStart, $rangeEnd, $monthlyRunRate, $granularity);
            if ($comparePreviousYear) {
                $prevStart = $rangeStart->copy()->subYear();
                $prevEnd = $rangeEnd->copy()->subYear();
                $previousYear[] = $this->sumPaidBetween($prevStart, $prevEnd);
            }
        }

        return [
            'granularity' => $granularity,
            'labels' => $labels,
            'datasets' => [
                ['key' => 'actual', 'label' => 'Chi phí thực tế (ĐNTT đã thanh toán)', 'data' => $actual],
                ['key' => 'budget', 'label' => 'Ngân sách vận hành (PĐX/tháng)', 'data' => $budget],
                ...($comparePreviousYear ? [
                    ['key' => 'previous_year', 'label' => 'Cùng kỳ năm trước', 'data' => $previousYear],
                ] : []),
            ],
        ];
    }

    private function sumPaidBetween(Carbon $from, Carbon $to): int
    {
        return (int) AiPaymentRequest::query()
            ->where('status', AiPaymentRequestStatus::Paid->value)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    private function budgetForBucket(Carbon $from, Carbon $to, int $monthlyRunRate, string $granularity): int
    {
        return match ($granularity) {
            'day' => (int) round($monthlyRunRate / max(1, $from->daysInMonth)),
            'month' => $monthlyRunRate,
            'quarter' => $monthlyRunRate * 3,
            'year' => $monthlyRunRate * 12,
            default => $monthlyRunRate,
        };
    }

    private function buildPeriod(Carbon $start, Carbon $end, string $granularity): \Carbon\CarbonPeriod
    {
        $step = match ($granularity) {
            'day' => '1 day',
            'month' => '1 month',
            'quarter' => '3 months',
            'year' => '1 year',
            default => '1 month',
        };

        return CarbonPeriod::create($start, $step, $end);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function bucketRange(Carbon $point, string $granularity): array
    {
        return match ($granularity) {
            'day' => [
                $point->copy()->startOfDay(),
                $point->copy()->endOfDay(),
                $point->format('d/m'),
            ],
            'quarter' => [
                $point->copy()->startOfQuarter(),
                $point->copy()->endOfQuarter(),
                'Q'.$point->quarter.'/'.$point->year,
            ],
            'year' => [
                $point->copy()->startOfYear(),
                $point->copy()->endOfYear(),
                (string) $point->year,
            ],
            default => [
                $point->copy()->startOfMonth(),
                $point->copy()->endOfMonth(),
                $point->format('m/Y'),
            ],
        };
    }

    /** @param  Builder<AiAccount>  $query */
    private function applyReportFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $q = mb_strtolower(trim((string) $filters['search']));
            $query->where(function (Builder $inner) use ($q) {
                $inner->whereRaw('LOWER(tool_name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(email_registered) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(allocated_to_name) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('purchaseProposal', function (Builder $p) use ($q) {
                        $p->whereRaw('LOWER(proposal_code) LIKE ?', ["%{$q}%"])
                            ->orWhereRaw('LOWER(proposer_name) LIKE ?', ["%{$q}%"]);
                    });
            });
        }

        if (! empty($filters['group_function']) && $filters['group_function'] !== 'all') {
            $query->where('group_function', $filters['group_function']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['lifecycle_status']) && $filters['lifecycle_status'] !== 'all') {
            $query->where('lifecycle_status', $filters['lifecycle_status']);
        }

        if (! empty($filters['tool']) && $filters['tool'] !== 'all') {
            $query->where('tool_name', $filters['tool']);
        }

        if (! empty($filters['purchase_from'])) {
            $query->whereDate('purchase_date', '>=', $filters['purchase_from']);
        }
        if (! empty($filters['purchase_to'])) {
            $query->whereDate('purchase_date', '<=', $filters['purchase_to']);
        }
        if (! empty($filters['expiry_from'])) {
            $query->whereDate('expiry_date', '>=', $filters['expiry_from']);
        }
        if (! empty($filters['expiry_to'])) {
            $query->whereDate('expiry_date', '<=', $filters['expiry_to']);
        }

        $proposalFilters = ! empty($filters['department']) && $filters['department'] !== 'all'
            || ! empty($filters['vendor']) && $filters['vendor'] !== 'all'
            || ! empty($filters['proposer']) && $filters['proposer'] !== 'all'
            || ! empty($filters['proposal_status']) && $filters['proposal_status'] !== 'all'
            || ! empty($filters['created_from'])
            || ! empty($filters['created_to']);

        if ($proposalFilters) {
            $query->where(function (Builder $outer) use ($filters) {
                $outer->whereHas('purchaseProposal', function (Builder $p) use ($filters) {
                    if (! empty($filters['department']) && $filters['department'] !== 'all') {
                        $dept = $filters['department'];
                        $p->where(function (Builder $d) use ($dept) {
                            $d->where('proposer_department', $dept)
                                ->orWhere('department_using', $dept);
                        });
                    }
                    if (! empty($filters['vendor']) && $filters['vendor'] !== 'all') {
                        $p->where('vendor_name', $filters['vendor']);
                    }
                    if (! empty($filters['proposer']) && $filters['proposer'] !== 'all') {
                        $p->where('proposer_name', $filters['proposer']);
                    }
                    if (! empty($filters['proposal_status']) && $filters['proposal_status'] !== 'all') {
                        $p->where('status', $filters['proposal_status']);
                    }
                    if (! empty($filters['created_from'])) {
                        $p->whereDate('created_at', '>=', $filters['created_from']);
                    }
                    if (! empty($filters['created_to'])) {
                        $p->whereDate('created_at', '<=', $filters['created_to']);
                    }
                });
            });
        }
    }

    /** @return array<string, mixed> */
    private function reportRow(AiAccount $account): array
    {
        $account->loadMissing(['purchaseProposal.creator.employee', 'purchaseProposal.reviewer.employee']);
        $proposal = $account->purchaseProposal;
        $monthly = $this->countableProposalCost->monthlyForAccountInBudget($account);
        $monthlyAccount = $this->costCalculator->monthlyForAccount($account);
        $monthsUsed = max(1, $account->purchase_date->diffInMonths($account->expiry_date));

        $reviewerName = $proposal?->reviewer?->employee?->full_name
            ?? $proposal?->reviewer?->username;

        return [
            'id' => $account->id,
            'proposal_code' => $proposal?->proposal_code,
            'tool_name' => $account->tool_name,
            'vendor_name' => $proposal?->vendor_name,
            'license_type' => $account->license_type,
            'user_name' => $account->allocated_to_name ?: $account->email_registered,
            'department' => $this->departmentLabelForAccount($account),
            'unit' => $proposal?->department_using ?: $proposal?->send_to,
            'registration_date' => $proposal?->planned_use_date?->format('Y-m-d'),
            'purchase_date' => $account->purchase_date->format('Y-m-d'),
            'activated_at' => $account->allocated_at?->format('Y-m-d'),
            'expiry_date' => $account->expiry_date->format('Y-m-d'),
            'months_used' => $monthsUsed,
            'cost_monthly' => $monthly > 0 ? $monthly : $monthlyAccount,
            'cost_yearly' => ($monthly > 0 ? $monthly : $monthlyAccount) * 12,
            'actual_cost' => $account->actual_purchase_cost,
            'status' => $account->status->value,
            'status_label' => $account->status->labelVi(),
            'lifecycle_status' => ($account->lifecycle_status ?? AiAccountLifecycleStatus::InUse)->value,
            'lifecycle_label' => ($account->lifecycle_status ?? AiAccountLifecycleStatus::InUse)->labelVi(),
            'approver_name' => $reviewerName,
            'proposer_name' => $proposal?->proposer_name,
            'group_function' => $account->group_function->value,
            'notes' => $account->notes ?? $proposal?->review_notes,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function reportFooterStats(array $rows): array
    {
        $totalMonthly = array_sum(array_column($rows, 'cost_monthly'));
        $count = count($rows);

        $byDept = [];
        $byTool = [];
        $byUnit = [];
        foreach ($rows as $row) {
            $d = $row['department'] ?? 'Khác';
            $byDept[$d] = ($byDept[$d] ?? 0) + (int) $row['cost_monthly'];
            $t = $row['tool_name'] ?? 'Khác';
            $byTool[$t] = ($byTool[$t] ?? 0) + (int) $row['cost_monthly'];
            $u = $row['unit'] ?? '—';
            $byUnit[$u] = ($byUnit[$u] ?? 0) + (int) $row['cost_monthly'];
        }

        arsort($byDept);
        arsort($byTool);
        arsort($byUnit);

        return [
            'account_count' => $count,
            'cost_total_monthly' => $totalMonthly,
            'cost_average_monthly' => $count > 0 ? (int) round($totalMonthly / $count) : 0,
            'by_department' => collect($byDept)->map(fn ($v, $k) => ['department' => $k, 'cost_monthly' => $v])->values()->all(),
            'by_product' => collect($byTool)->map(fn ($v, $k) => ['tool_name' => $k, 'cost_monthly' => $v])->values()->all(),
            'by_unit' => collect($byUnit)->map(fn ($v, $k) => ['unit' => $k, 'cost_monthly' => $v])->values()->all(),
        ];
    }

    private function departmentLabelForAccount(AiAccount $account): string
    {
        $proposal = $account->purchaseProposal;
        $dept = $proposal?->department_using
            ?: $proposal?->proposer_department
            ?: $account->group_function->label();

        return trim((string) $dept) ?: 'Khác';
    }

    /** @param  Collection<int, AiAccount>  $accounts */
    private function departmentKeysFromAccounts(Collection $accounts): Collection
    {
        return $accounts->map(fn (AiAccount $a) => $this->departmentLabelForAccount($a))->unique();
    }
}
