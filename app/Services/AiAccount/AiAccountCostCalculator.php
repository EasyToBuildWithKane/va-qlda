<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountStatus;

class AiAccountCostCalculator
{
    public function monthlyAmount(int $costAmount, AiAccountCostUnit $unit): int
    {
        return match ($unit) {
            AiAccountCostUnit::Monthly => $costAmount,
            AiAccountCostUnit::Yearly => (int) round($costAmount / 12),
            AiAccountCostUnit::OneTime => 0,
        };
    }

    public function monthlyForAccount(AiAccount $account): int
    {
        if ($account->status === AiAccountStatus::Cancelled) {
            return 0;
        }

        return $this->monthlyAmount($account->cost_amount, $account->cost_unit);
    }

    public function formatVnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' đ';
    }

    public function usdToVnd(float $usd): int
    {
        $rate = (int) config('ai_accounts.exchange_rate', 25_500);

        return (int) round($usd * $rate);
    }
}
