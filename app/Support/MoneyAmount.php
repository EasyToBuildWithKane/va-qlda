<?php

namespace App\Support;

/**
 * Tiền tệ: cắt phần thập phân thừa, không làm tròn (CLM / VNĐ).
 */
final class MoneyAmount
{
    public static function truncate(float $amount, int $decimals = 2): float
    {
        if ($decimals <= 0) {
            return (float) floor($amount);
        }
        $factor = 10 ** $decimals;

        return floor($amount * $factor) / $factor;
    }
}
