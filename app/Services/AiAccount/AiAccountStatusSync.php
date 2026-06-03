<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountStatus;
use Carbon\Carbon;

class AiAccountStatusSync
{
    public function resolve(AiAccount $account, ?Carbon $today = null): AiAccountStatus
    {
        if ($account->status === AiAccountStatus::Cancelled) {
            return AiAccountStatus::Cancelled;
        }

        $today = ($today ?? now())->startOfDay();
        $expiry = $account->expiry_date->copy()->startOfDay();

        if ($expiry->lt($today)) {
            return AiAccountStatus::Expired;
        }

        $daysLeft = (int) $today->diffInDays($expiry, false);
        if ($daysLeft <= $account->notify_before_days) {
            return AiAccountStatus::ExpiringSoon;
        }

        return AiAccountStatus::Active;
    }

    public function daysUntilExpiry(AiAccount $account, ?Carbon $today = null): int
    {
        return max(0, $this->daysUntilExpirySigned($account, $today));
    }

    /** Âm = đã quá hạn. */
    public function daysUntilExpirySigned(AiAccount $account, ?Carbon $today = null): int
    {
        $today = ($today ?? now())->startOfDay();
        $expiry = $account->expiry_date->copy()->startOfDay();

        return (int) $today->diffInDays($expiry, false);
    }

    public function syncAndSave(AiAccount $account, ?Carbon $today = null): void
    {
        if ($account->status_locked_at !== null) {
            return;
        }

        $next = $this->resolve($account, $today);
        if ($account->status !== $next) {
            $account->status = $next;
            $account->save();
        }
    }

    public function syncCollection(iterable $accounts, ?Carbon $today = null): void
    {
        foreach ($accounts as $account) {
            $this->syncAndSave($account, $today);
        }
    }
}
