<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Models\Vendor;
use App\Support\Enums\ContractAttachmentCategory;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tổng hợp số liệu cho CLM Dashboard (Phân hệ 1): KPI, tình trạng, chi phí
 * theo tháng, top NCC, và danh sách cảnh báo. Tải dữ liệu một lần rồi tính
 * trong PHP (tránh N+1) — theo mẫu PerformanceMetrics.
 */
class ContractMetricsEngine
{
    public function __construct(private readonly ContractRenewalCalculator $calculator) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $contracts = Contract::query()->get();
        $today = Carbon::today();
        $thisYear = (int) $today->format('Y');

        return [
            'kpis' => $this->kpis($contracts, $thisYear),
            'statusDistribution' => $this->statusDistribution($contracts),
            'costTrend' => $this->costTrend($contracts, $thisYear),
            'topVendors' => $this->topVendors($contracts),
            'alerts' => $this->alerts($contracts, $today),
            'paymentBreakdown' => $this->paymentBreakdown($contracts),
            'generated_at' => $today->toDateString(),
        ];
    }

    /**
     * Alert Center (Phân hệ 8): toàn bộ cảnh báo realtime + tổng hợp theo mức
     * độ và loại. Bao gồm: sắp hết hạn, đã hết hạn, chưa thanh toán, thiếu hồ
     * sơ hợp đồng (chưa có file/link loại "Hợp đồng").
     *
     * @return array<string, mixed>
     */
    public function buildAlerts(): array
    {
        $today = Carbon::today();
        $window = $this->calculator->expiringWindowDays();

        $contracts = Contract::query()
            ->with('vendor')
            ->withCount(['attachments as contract_doc_count' => fn ($q) => $q->where('category', ContractAttachmentCategory::Contract->value)])
            ->get();

        $items = [];
        foreach ($contracts as $c) {
            $days = $this->calculator->daysUntilExpiry($c, $today);

            if ($days !== null && $days < 0 && $c->status !== ContractStatus::Terminated) {
                $items[] = $this->alertItem($c, 'expired', 'critical', $days);
            } elseif ($days !== null && $days <= $window && $c->status->isLive()) {
                $level = $days <= 7 ? 'critical' : ($days <= 30 ? 'high' : 'medium');
                $items[] = $this->alertItem($c, 'expiring', $level, $days);
            }

            if ($c->payment_status === ContractPaymentStatus::Unpaid && $c->status->isLive()) {
                $items[] = $this->alertItem($c, 'unpaid', 'high', $days);
            }

            if ($c->status->isLive() && (int) ($c->contract_doc_count ?? 0) === 0) {
                $items[] = $this->alertItem($c, 'missing_docs', 'medium', $days);
            }
        }

        usort($items, fn ($a, $b) => self::levelRank($a['level']) <=> self::levelRank($b['level']));

        return [
            'items' => $items,
            'summary' => $this->alertSummary($items),
            'generated_at' => $today->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alertItem(Contract $c, string $type, string $level, ?int $days): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'vendor' => $c->vendor?->name,
            'type' => $type,
            'level' => $level,
            'days_until_expiry' => $days,
            'expiry_date' => $c->expiry_date?->toDateString(),
        ];
    }

    private static function levelRank(string $level): int
    {
        return ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$level] ?? 9;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function alertSummary(array $items): array
    {
        $by = fn (string $key, string $val) => count(array_filter($items, fn ($i) => $i[$key] === $val));

        return [
            'total' => count($items),
            'critical' => $by('level', 'critical'),
            'high' => $by('level', 'high'),
            'medium' => $by('level', 'medium'),
            'low' => $by('level', 'low'),
            'expiring' => $by('type', 'expiring'),
            'expired' => $by('type', 'expired'),
            'unpaid' => $by('type', 'unpaid'),
            'missing_docs' => $by('type', 'missing_docs'),
        ];
    }

    /**
     * Reporting (Phân hệ 9): bảng tổng hợp theo nhiều chiều cho trang báo cáo
     * + xuất file. Mỗi chiều: [{label, count, annual_cost, lifecycle_cost}].
     *
     * @return array<string, mixed>
     */
    public function buildReport(): array
    {
        $contracts = Contract::query()->with('vendor', 'category')->get();

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
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0)), 2),
                'lifecycle_cost' => round((float) $group->sum(fn (Contract $c) => (float) ($c->lifecycle_cost ?? 0)), 2),
            ]);

        $rows = $sortByLabel
            ? $rows->sortKeys()
            : $rows->sortByDesc('annual_cost');

        return $rows->values()->all();
    }

    /**
     * Số liệu cho Cost Management (Phân hệ 7): chi phí theo NCC / đơn vị /
     * nhóm dịch vụ / quý, và dự báo ngân sách năm tới.
     *
     * @return array<string, mixed>
     */
    public function buildCost(): array
    {
        $contracts = Contract::query()->with('vendor', 'category')->get();
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
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0)), 2),
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

                return (float) ($c->monthly_cost ?? (($c->annual_cost ?? 0) / 12)) * 3;
            }), 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Dự báo ngân sách năm tới = tổng annual_cost của hợp đồng còn sống hoặc
     * tự động gia hạn (đã loại bỏ hợp đồng đã thanh lý/hết hạn không gia hạn).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{total:float, auto_renew:float, manual:float}
     */
    private function forecastNextYear(Collection $contracts): array
    {
        $relevant = $contracts->filter(
            fn (Contract $c) => $c->status->isLive() || $c->auto_renew,
        );

        $autoRenew = (float) $relevant->where('auto_renew', true)->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0));
        $total = (float) $relevant->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0));

        return [
            'total' => round($total, 2),
            'auto_renew' => round($autoRenew, 2),
            'manual' => round($total - $autoRenew, 2),
        ];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array<string, mixed>>
     */
    private function kpis(Collection $contracts, int $thisYear): array
    {
        $totalValue = (float) $contracts->sum(fn (Contract $c) => (float) ($c->lifecycle_cost ?? 0));
        $costThisYear = (float) $contracts
            ->filter(fn (Contract $c) => $c->effective_date === null || (int) $c->effective_date->format('Y') <= $thisYear)
            ->filter(fn (Contract $c) => $c->expiry_date === null || (int) $c->expiry_date->format('Y') >= $thisYear)
            ->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0));
        $budgetNextYear = (float) $contracts
            ->filter(fn (Contract $c) => $c->status->isLive())
            ->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0));

        return [
            ['key' => 'total_contracts', 'label' => 'Tổng hợp đồng', 'value' => $contracts->count(), 'tone' => 'neutral'],
            ['key' => 'total_value', 'label' => 'Tổng giá trị', 'value' => $totalValue, 'format' => 'currency', 'tone' => 'neutral'],
            ['key' => 'cost_this_year', 'label' => 'Chi phí năm nay', 'value' => $costThisYear, 'format' => 'currency', 'tone' => 'neutral'],
            ['key' => 'budget_next_year', 'label' => 'Ngân sách năm tới', 'value' => $budgetNextYear, 'format' => 'currency', 'tone' => 'neutral'],
            ['key' => 'total_vendors', 'label' => 'Nhà cung cấp', 'value' => Vendor::query()->count(), 'tone' => 'neutral'],
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

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{value:string, label:string, color:string, count:int}>
     */
    private function paymentBreakdown(Collection $contracts): array
    {
        return collect(ContractPaymentStatus::cases())
            ->map(fn (ContractPaymentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'count' => $contracts->where('payment_status', $s)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Chi phí năm (annual_cost) phân bổ đều theo 12 tháng của năm hiện tại,
     * chỉ tính các hợp đồng còn hiệu lực trong tháng đó.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array{labels:array<int,string>, data:array<int,float>}
     */
    private function costTrend(Collection $contracts, int $thisYear): array
    {
        $labels = [];
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $periodStart = Carbon::create($thisYear, $month, 1)->startOfMonth();
            $periodEnd = (clone $periodStart)->endOfMonth();
            $labels[] = 'T'.$month;

            $monthly = $contracts->sum(function (Contract $c) use ($periodStart, $periodEnd) {
                $effective = $c->effective_date ?? $c->signed_date;
                if ($effective && $effective->greaterThan($periodEnd)) {
                    return 0;
                }
                if ($c->expiry_date && $c->expiry_date->lessThan($periodStart)) {
                    return 0;
                }

                return (float) ($c->monthly_cost ?? (($c->annual_cost ?? 0) / 12));
            });

            $data[] = round((float) $monthly, 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array{vendor_id:int|null, name:string, count:int, annual_cost:float}>
     */
    private function topVendors(Collection $contracts): array
    {
        $vendorNames = Vendor::query()->pluck('name', 'id');

        return $contracts
            ->groupBy('vendor_id')
            ->map(fn (Collection $group, $vendorId) => [
                'vendor_id' => $vendorId !== '' ? (int) $vendorId : null,
                'name' => $vendorId && isset($vendorNames[$vendorId]) ? $vendorNames[$vendorId] : 'Chưa gán NCC',
                'count' => $group->count(),
                'annual_cost' => round((float) $group->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0)), 2),
            ])
            ->sortByDesc('annual_cost')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Danh sách cảnh báo realtime (preview cho dashboard).
     *
     * @param  Collection<int, Contract>  $contracts
     * @return array<int, array<string, mixed>>
     */
    private function alerts(Collection $contracts, Carbon $today): array
    {
        $window = $this->calculator->expiringWindowDays();

        return $contracts
            ->map(function (Contract $c) use ($today, $window) {
                $days = $this->calculator->daysUntilExpiry($c, $today);

                $type = null;
                $level = null;
                if ($days !== null && $days < 0 && ! in_array($c->status, [ContractStatus::Terminated], true)) {
                    $type = 'expired';
                    $level = 'critical';
                } elseif ($days !== null && $days <= $window && $c->status->isLive()) {
                    $type = 'expiring';
                    $level = $days <= 7 ? 'critical' : ($days <= 30 ? 'high' : 'medium');
                } elseif ($c->payment_status === ContractPaymentStatus::Unpaid && $c->status->isLive()) {
                    $type = 'unpaid';
                    $level = 'high';
                }

                if ($type === null) {
                    return null;
                }

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'type' => $type,
                    'level' => $level,
                    'days_until_expiry' => $days,
                    'expiry_date' => $c->expiry_date?->toDateString(),
                ];
            })
            ->filter()
            ->sortBy('days_until_expiry')
            ->take(20)
            ->values()
            ->all();
    }
}
