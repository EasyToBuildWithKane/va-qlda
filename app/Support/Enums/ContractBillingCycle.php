<?php

namespace App\Support\Enums;

/**
 * Chu kỳ thanh toán / gia hạn hợp đồng. Map từ cột "Chu Kỳ" của file quản lý
 * phần mềm (Hàng năm / Một lần); thêm tháng & quý để nâng cấp về sau.
 */
enum ContractBillingCycle: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Một lần',
            self::Monthly => 'Hàng tháng',
            self::Quarterly => 'Hàng quý',
            self::Annual => 'Hàng năm',
        };
    }

    /** Số tháng tương ứng một chu kỳ (one_time = null vì không lặp). */
    public function months(): ?int
    {
        return match ($this) {
            self::OneTime => null,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Annual => 12,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ], self::cases());
    }
}
