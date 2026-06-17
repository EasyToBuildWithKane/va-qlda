<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Models\ContractFinance;
use App\Models\Vendor;
use App\Support\Enums\ContractStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tổng hợp số liệu cho CLM Dashboard / Chi phí / Báo cáo.
 *
 * Tiền tệ ưu tiên lấy từ `contract_finances` (init_fee = triển khai mới,
 * maintenance_fee = duy trì, total/renewal_cost = dòng tiền); fallback về
 * `annual_cost` / `monthly_cost` của hợp đồng khi không có dòng finance.
 * Tải dữ liệu một lần rồi tính trong PHP (tránh N+1) — mẫu PerformanceMetrics.
 */
class ContractMetricsEngine
{
    public function __construct(private readonly ContractRenewalCalculator $calculator) {}

    /**
     * Dashboard (Phân hệ 1).
     *
     * @param  'month'|'quarter'|'year'  $maintenancePeriod
     * @return array<string, mixed>
     */
    public function build(string $maintenancePeriod = 'year'): array
    {
        $contracts = Contract::query()->with('vendor', 'category', 'finances')->get();
        $today = Carbon::today();
        $thisYear = (int) $today->format('Y');

        $maintenance = $this->maintenanceByPeriod($contracts);
        $expiringSoon = $this->expiringSoon($contracts, $today, 30);
        $deployment = $this->deploymentThisYear($contracts, $thisYear);
        $cashflow = $this->cashflowByMonth($contracts, $thisYear);
        $lowScore = $this->vendorLowScoreCount();

        return [
            'kpis' => $this->kpis($contracts, $today, [
                'maintenance' => $maintenance,
                'maintenance_period' => $maintenancePeriod,
                'expiring_30' => count($expiringSoon),
                'deployment' => $deployment,
                'low_score' => $lowScore,
                'cashflow_year' => array_sum($cashflow['data']),
            ]),
            'maintenance' => $maintenance,
            'maintenance_period' => $maintenancePeriod,
            'statusDistribution' => $this->statusDistribution($contracts),
            'costByCategory' => $this->costByCategory($contracts),
            'costByExpiryMonth' => $this->costByExpiryMonth($contracts),
            'topVendors' => $this->topVendors($contracts, 10),
            'cashflow' => $cashflow,
            'expiringSoon' => $expiringSoon,
            'this_year' => $thisYear,
            'generated_at' => $today->toDateString(),
        ];
    }

    // ── Tiền theo finances ─────────────────────────────────────────────────

    /** Chi phí năm quy đổi của 1 hợp đồng (đã load finances). */
    private function resolvedAnnual(Contract $c): float
    {
        return $c->annualCostResolved();
    }

    /** Dòng tiền (cashflow) 1 hợp đồng = tổng `total` finances, fallback annual. */
    private function contractCashflow(Contract $c): float
    {
        if ($c->relationLoaded('finances') && $c->finances->isNotEmpty()) {
            $total = (float) $c->finances->sum(fn (ContractFinance $f) => (float) ($f->total ?? 0));
            if ($total > 0) {
                return round($total, 2);
            }
        }

        return $this->resolvedAnnual($c);
    }

    /**
     * Chi phí duy trì = tổng maintenance_fee (xem là theo năm); quy về kỳ.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{month:float, quarter:float, year:float}
     */
    private function maintenanceByPeriod(Collection $contracts): array
    {
        $annual = (float) $contracts->sum(function (Contract $c) {
            if ($c->relationLoaded('finances') && $c->finances->isNotEmpty()) {
                return (float) $c->finances->sum(fn (ContractFinance $f) => (float) ($f->maintenance_fee ?? 0));
            }

            return 0.0;
        });

        // Nếu không có finance duy trì, fallback tổng chi phí năm quy đổi.
        if ($annual <= 0) {
            $annual = (float) $contracts->sum(fn (Contract $c) => $this->resolvedAnnual($c));
        }

        return [
            'month' => round($annual / 12, 2),
            'quarter' => round($annual / 4, 2),
            'year' => round($annual, 2),
        ];
    }

    /**
     * Chi phí triển khai mới trong năm = tổng init_fee có used_date thuộc năm
     * (fallback: hiệu lực/ký trong năm khi finance thiếu used_date).
     *
     * @param  Collection<int, Contract>  $contracts
     */
    private function deploymentThisYear(Collection $contracts, int $year): float
    {
        $sum = 0.0;
        foreach ($contracts as $c) {
            if (! $c->relationLoaded('finances')) {
                continue;
            }
            foreach ($c->finances as $f) {
                $init = (float) ($f->init_fee ?? 0);
                if ($init <= 0) {
                    continue;
                }
                $date = $f->used_date ?? $c->effective_date ?? $c->signed_date;
                if ($date && (int) $date->format('Y') === $year) {
                    $sum += $init;
                }
            }
        }

        return round($sum, 2);
    }

    /**
     * Phân bổ dòng tiền theo 12 tháng của năm (total finances, fallback gia hạn).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{labels:array<int,string>, data:array<int,float>}
     */
    private function cashflowByMonth(Collection $contracts, int $year): array
    {
        $data = array_fill(0, 12, 0.0);

        foreach ($contracts as $c) {
            if (! $c->relationLoaded('finances')) {
                continue;
            }
            foreach ($c->finances as $f) {
                $amount = (float) ($f->total ?? 0);
                if ($amount === 0.0) {
                    $amount = (float) ($f->renewal_cost ?? 0);
                }
                if ($amount === 0.0) {
                    continue;
                }
                $date = $f->used_date ?? $c->effective_date ?? $c->signed_date;
                if ($date && (int) $date->format('Y') === $year) {
                    $data[(int) $date->format('n') - 1] += $amount;
                }
            }
        }

        return [
            'labels' => array_map(fn ($m) => 'T'.$m, range(1, 12)),
            'data' => array_map(fn ($v) => round($v, 2), $data),
        ];
    }

    // ── Charts ──────────────────────────────────────────────────────────────

    /**
     * Donut chi phí theo nhóm dịch vụ.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{label:string, value:float, count:int}>
     */
    private function costByCategory(Collection $contracts): array
    {
        return $contracts
            ->groupBy(fn (Contract $c) => $c->category?->name ?? 'Khác')
            ->map(fn (Collection $group, $label) => [
                'label' => (string) $label,
                'value' => round((float) $group->sum(fn (Contract $c) => $this->resolvedAnnual($c)), 2),
                'count' => $group->count(),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Bar chi phí theo tháng hết hạn (gộp mọi năm theo tháng 1–12).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{labels:array<int,string>, data:array<int,float>}
     */
    private function costByExpiryMonth(Collection $contracts): array
    {
        $data = array_fill(0, 12, 0.0);

        foreach ($contracts as $c) {
            if (! $c->expiry_date) {
                continue;
            }
            $data[(int) $c->expiry_date->format('n') - 1] += $this->resolvedAnnual($c);
        }

        return [
            'labels' => array_map(fn ($m) => 'T'.$m, range(1, 12)),
            'data' => array_map(fn ($v) => round($v, 2), $data),
        ];
    }

    /**
     * Top NCC theo chi phí + dòng tiền.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{vendor_id:int|null, name:string, count:int, annual_cost:float, cashflow:float}>
     */
    private function topVendors(Collection $contracts, int $limit): array
    {
        $vendorNames = Vendor::query()->pluck('name', 'id');

        return $contracts
            ->groupBy('vendor_id')
            ->map(fn (Collection $group, $vendorId) => [
                'vendor_id' => $vendorId !== '' && $vendorId !== null ? (int) $vendorId : null,
                'name' => $vendorId && isset($vendorNames[$vendorId]) ? $vendorNames[$vendorId] : 'Chưa gán NCC',
                'count' => $group->count(),
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => $this->resolvedAnnual($c)), 2),
                'cashflow' => round((float) $group->sum(fn (Contract $c) => $this->contractCashflow($c)), 2),
            ])
            ->sortByDesc('annual_cost')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Datatable hợp đồng sắp hết hạn trong `$within` ngày.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array<string, mixed>>
     */
    private function expiringSoon(Collection $contracts, Carbon $today, int $within): array
    {
        return $contracts
            ->map(function (Contract $c) use ($today, $within) {
                $days = $this->calculator->daysUntilExpiry($c, $today);
                if ($days === null || $days < 0 || $days > $within || ! $c->status->isLive()) {
                    return null;
                }

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'vendor' => $c->vendor?->name,
                    'expiry_date' => $c->expiry_date?->toDateString(),
                    'days_until_expiry' => $days,
                    'annual_cost' => $this->resolvedAnnual($c),
                    'status' => ['value' => $c->status->value, 'label' => $c->status->label(), 'color' => $c->status->color()],
                ];
            })
            ->filter()
            ->sortBy('days_until_expiry')
            ->values()
            ->all();
    }

    /** #NCC có đánh giá gần nhất < 7 điểm. */
    private function vendorLowScoreCount(): int
    {
        return Vendor::query()
            ->with('latestReview')
            ->get()
            ->filter(fn (Vendor $v) => $v->latestReview?->total_score !== null && (float) $v->latestReview->total_score < 7)
            ->count();
    }

    // ── KPI strip ───────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, Contract>  $contracts
     * @param  array<string, mixed>  $pre
     * @return array<int, array<string, mixed>>
     */
    private function kpis(Collection $contracts, Carbon $today, array $pre): array
    {
        $totalValue = (float) $contracts->sum(fn (Contract $c) => $this->resolvedAnnual($c));
        $overdueOr15 = $contracts->filter(function (Contract $c) use ($today) {
            if ($c->status === ContractStatus::Terminated) {
                return false;
            }
            $days = $this->calculator->daysUntilExpiry($c, $today);
            if ($days === null) {
                return false;
            }

            return $days < 0 || ($days <= 15 && $c->status->isLive());
        })->count();

        /** @var array{month:float,quarter:float,year:float} $m */
        $m = $pre['maintenance'];
        $maintenanceValue = $m[$pre['maintenance_period']] ?? $m['year'];

        return [
            ['key' => 'total_contracts', 'label' => 'Hồ sơ hợp đồng', 'value' => $contracts->count()],
            ['key' => 'maintenance', 'label' => 'Chi phí duy trì', 'value' => $maintenanceValue, 'format' => 'currency'],
            ['key' => 'total_value', 'label' => 'Tổng giá trị', 'value' => round($totalValue, 2), 'format' => 'currency'],
            ['key' => 'expiring_30', 'label' => 'Sắp hết hạn ≤30 ngày', 'value' => $pre['expiring_30']],
            ['key' => 'deployment', 'label' => 'Triển khai mới (năm)', 'value' => $pre['deployment'], 'format' => 'currency'],
            ['key' => 'low_score', 'label' => 'NCC chấm < 7', 'value' => $pre['low_score']],
            ['key' => 'cashflow_year', 'label' => 'Dòng tiền trong năm', 'value' => round((float) $pre['cashflow_year'], 2), 'format' => 'currency'],
            ['key' => 'overdue_15', 'label' => 'Quá hạn / < 15 ngày', 'value' => $overdueOr15],
        ];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{value:string, label:string, color:string, count:int}>
     */
    private function statusDistribution(Collection $contracts): array
    {
        return collect(ContractStatus::cases())
            ->map(fn (ContractStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'count' => $contracts->where('status', $s)->count(),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    // ── Reporting (Phân hệ 9) ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function buildReport(): array
    {
        $contracts = Contract::query()->with('vendor', 'category', 'finances')->get();

        return [
            'byVendor' => $this->reportRows($contracts, fn (Contract $c) => $c->vendor?->name ?? 'Chưa gán NCC'),
            'byCategory' => $this->reportRows($contracts, fn (Contract $c) => $c->category?->name ?? 'Khác'),
            'byUnit' => $this->reportRows($contracts, fn (Contract $c) => $c->using_unit ?: 'Chưa gán đơn vị'),
            'byYear' => $this->reportRows(
                $contracts->filter(fn (Contract $c) => $c->expiry_date !== null),
                fn (Contract $c) => (string) $c->expiry_date->format('Y'),
                sortByLabel: true,
            ),
        ];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{label:string, count:int, annual_cost:float, lifecycle_cost:float}>
     */
    private function reportRows(Collection $contracts, callable $keyBy, bool $sortByLabel = false): array
    {
        $rows = $contracts
            ->groupBy($keyBy)
            ->map(fn (Collection $group, $label) => [
                'label' => (string) $label,
                'count' => $group->count(),
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => $this->resolvedAnnual($c)), 2),
                'lifecycle_cost' => round((float) $group->sum(fn (Contract $c) => (float) ($c->lifecycle_cost ?? 0)), 2),
            ]);

        $rows = $sortByLabel
            ? $rows->sortKeys()
            : $rows->sortByDesc('annual_cost');

        return $rows->values()->all();
    }

    // ── Cost Management (Phân hệ 7) ───────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function buildCost(): array
    {
        $contracts = Contract::query()->with('vendor', 'category', 'finances')->get();
        $thisYear = (int) Carbon::today()->format('Y');

        return [
            'byVendor' => $this->groupCost($contracts, fn (Contract $c) => $c->vendor?->name ?? 'Chưa gán NCC'),
            'byUnit' => $this->groupCost($contracts, fn (Contract $c) => $c->using_unit ?: 'Chưa gán đơn vị'),
            'byCategory' => $this->groupCost($contracts, fn (Contract $c) => $c->category?->name ?? 'Khác'),
            'byQuarter' => $this->costByQuarter($contracts, $thisYear),
            'forecast' => $this->forecastNextYear($contracts),
            'this_year' => $thisYear,
        ];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{label:string, annual_cost:float, count:int}>
     */
    private function groupCost(Collection $contracts, callable $keyBy): array
    {
        return $contracts
            ->groupBy($keyBy)
            ->map(fn (Collection $group, $label) => [
                'label' => (string) $label,
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => $this->resolvedAnnual($c)), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('annual_cost')
            ->values()
            ->take(12)
            ->all();
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array{labels:array<int,string>, data:array<int,float>}
     */
    private function costByQuarter(Collection $contracts, int $thisYear): array
    {
        $labels = ['Q1', 'Q2', 'Q3', 'Q4'];
        $data = [0.0, 0.0, 0.0, 0.0];

        for ($q = 0; $q < 4; $q++) {
            $start = Carbon::create($thisYear, $q * 3 + 1, 1)->startOfMonth();
            $end = (clone $start)->addMonths(2)->endOfMonth();
            $data[$q] = round((float) $contracts->sum(function (Contract $c) use ($start, $end) {
                $effective = $c->effective_date ?? $c->signed_date;
                if ($effective && $effective->greaterThan($end)) {
                    return 0;
                }
                if ($c->expiry_date && $c->expiry_date->lessThan($start)) {
                    return 0;
                }

                return $this->resolvedAnnual($c) / 4;
            }), 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Dự báo ngân sách năm tới = tổng chi phí năm của hợp đồng còn sống / tự gia hạn.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{total:float, auto_renew:float, manual:float}
     */
    private function forecastNextYear(Collection $contracts): array
    {
        $relevant = $contracts->filter(
            fn (Contract $c) => $c->status->isLive() || $c->auto_renew,
        );

        $autoRenew = (float) $relevant->where('auto_renew', true)->sum(fn (Contract $c) => $this->resolvedAnnual($c));
        $total = (float) $relevant->sum(fn (Contract $c) => $this->resolvedAnnual($c));

        return [
            'total' => round($total, 2),
            'auto_renew' => round($autoRenew, 2),
            'manual' => round($total - $autoRenew, 2),
        ];
    }
}
