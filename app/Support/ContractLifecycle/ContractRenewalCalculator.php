<?php

namespace App\Support\ContractLifecycle;

use App\Models\Contract;
use App\Support\Enums\ContractStatus;
use Illuminate\Support\Carbon;

/**
 * Tính toán ngày tới hạn + xác định trạng thái vòng đời theo `expiry_date`.
 * Dùng chung bởi command nhắc gia hạn và Dashboard/Alert Center.
 */
class ContractRenewalCalculator
{
    /** Các mốc nhắc gia hạn mặc định (ngày) khi chưa cấu hình. */
    public const DEFAULT_MILESTONES = [90, 60, 30, 7];

    /** Số ngày coi là "sắp hết hạn" để chuyển status (mốc lớn nhất). */
    public function expiringWindowDays(): int
    {
        $milestones = $this->milestones();

        return $milestones === [] ? 90 : max($milestones);
    }

    /**
     * Các mốc nhắc cấu hình ở /settings (clm.renewal_alert_days), giảm dần.
     *
     * @return array<int, int>
     */
    public function milestones(): array
    {
        $configured = config('clm.renewal_alert_days', self::DEFAULT_MILESTONES);

        if (is_string($configured)) {
            $configured = array_map('trim', explode(',', $configured));
        }

        $days = collect((array) $configured)
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $days === [] ? self::DEFAULT_MILESTONES : $days;
    }

    public function daysUntilExpiry(Contract $contract, ?Carbon $today = null): ?int
    {
        if (! $contract->expiry_date) {
            return null;
        }

        return ($today ?? Carbon::today())->diffInDays($contract->expiry_date, false);
    }

    /**
     * Trạng thái nên có dựa trên ngày hết hạn — không đụng tới các trạng thái
     * do người dùng kiểm soát thủ công (draft, pending_renewal, terminated).
     */
    public function deriveStatus(Contract $contract, ?Carbon $today = null): ?ContractStatus
    {
        $manual = [ContractStatus::Draft, ContractStatus::PendingRenewal, ContractStatus::Terminated, ContractStatus::Addendum];
        if (in_array($contract->status, $manual, true)) {
            return null;
        }

        $days = $this->daysUntilExpiry($contract, $today);
        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return ContractStatus::Expired;
        }

        if ($days <= $this->expiringWindowDays()) {
            return ContractStatus::ExpiringSoon;
        }

        return ContractStatus::Active;
    }

    /** Mốc nhắc nhỏ nhất mà số ngày còn lại rơi vào (vd 30, 7) hoặc null. */
    public function matchedMilestone(int $daysUntilExpiry): ?int
    {
        $matched = null;

        foreach ($this->milestones() as $milestone) {
            if ($daysUntilExpiry <= $milestone) {
                // mốc giảm dần → lần gán cuối là mốc nhỏ nhất phù hợp
                $matched = $milestone;
            }
        }

        return $matched;
    }
}
