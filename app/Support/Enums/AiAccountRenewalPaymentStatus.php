<?php

namespace App\Support\Enums;

enum AiAccountRenewalPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function labelVi(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Unpaid => 'amber',
            self::Paid => 'emerald',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
        ], self::cases());
    }
}
