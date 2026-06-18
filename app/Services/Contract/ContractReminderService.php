<?php

namespace App\Services\Contract;

use App\Models\AppNotification;
use App\Models\Contract;
use App\Models\SystemAccount;
use App\Services\NotificationService;
use App\Support\ContractActivityLogger;
use App\Support\ContractLifecycle\ContractRenewalCalculator;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\NotificationType;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Carbon;

/**
 * Quét hợp đồng theo `expiry_date`, đồng bộ trạng thái vòng đời và tạo cảnh
 * báo (AppNotification) cho admin/lead. Idempotent trong ngày — không tạo
 * trùng cảnh báo cho cùng hợp đồng + loại + ngày.
 */
class ContractReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ContractRenewalCalculator $calculator,
    ) {}

    /**
     * @return array{synced:int, notified:int}
     */
    public function run(?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $synced = $this->syncStatuses($today);

        if (! config('clm.alert_enabled', true)) {
            return ['synced' => $synced, 'notified' => 0];
        }

        $recipients = SystemAccount::query()
            ->where('is_active', true)
            ->whereIn('role', [SystemRole::SuperAdmin->value, SystemRole::Admin->value, SystemRole::Lead->value])
            ->get();

        if ($recipients->isEmpty()) {
            return ['synced' => $synced, 'notified' => 0];
        }

        $window = $this->calculator->expiringWindowDays();
        $notified = 0;

        $contracts = Contract::query()
            ->whereNotNull('expiry_date')
            ->whereNotIn('status', [ContractStatus::Terminated->value, ContractStatus::Draft->value])
            ->get();

        foreach ($contracts as $contract) {
            $days = $this->calculator->daysUntilExpiry($contract, $today);
            if ($days === null) {
                continue;
            }

            if ($days < 0) {
                if ($this->dispatch($contract, $recipients, NotificationType::SystemContractExpired, $days, $today)) {
                    $notified++;
                }

                continue;
            }

            if ($days <= $window && $this->calculator->matchedMilestone($days) !== null) {
                if ($this->dispatch($contract, $recipients, NotificationType::SystemContractExpiry, $days, $today)) {
                    $notified++;
                }
            }
        }

        return ['synced' => $synced, 'notified' => $notified];
    }

    private function syncStatuses(Carbon $today): int
    {
        $synced = 0;

        Contract::query()->whereNotNull('expiry_date')->get()->each(function (Contract $contract) use ($today, &$synced) {
            $target = $this->calculator->deriveStatus($contract, $today);
            if ($target !== null && $target !== $contract->status) {
                $from = $contract->status->value;
                $contract->update(['status' => $target->value]);
                ContractActivityLogger::statusSynced($contract, $from, $target->value);
                $synced++;
            }
        });

        return $synced;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SystemAccount>  $recipients
     */
    private function dispatch(Contract $contract, $recipients, NotificationType $type, int $days, Carbon $today): bool
    {
        if ($this->alreadyNotifiedToday($contract, $type, $today)) {
            return false;
        }

        $expiryLabel = $contract->expiry_date?->format('d/m/Y');
        $title = $type === NotificationType::SystemContractExpired
            ? '🔴 Hợp đồng đã hết hạn'
            : '⚠️ Hợp đồng sắp hết hạn';

        $body = implode("\n", array_filter([
            'Mã: '.$contract->code,
            'Hợp đồng: '.$contract->name,
            $expiryLabel ? 'Hết hạn: '.$expiryLabel.($days >= 0 ? " (còn {$days} ngày)" : ' (quá '.abs($days).' ngày)') : null,
        ]));

        $this->notifications->notify(
            $recipients,
            $type,
            $title,
            $body,
            [
                'action_url' => "/contracts/{$contract->id}",
                'entity_type' => 'contract',
                'entity_id' => $contract->id,
                'meta' => ['days_until_expiry' => $days],
            ],
        );

        return true;
    }

    private function alreadyNotifiedToday(Contract $contract, NotificationType $type, Carbon $today): bool
    {
        return AppNotification::query()
            ->where('entity_type', 'contract')
            ->where('entity_id', $contract->id)
            ->where('type', $type->value)
            ->whereDate('created_at', $today->toDateString())
            ->exists();
    }
}
