<?php

namespace App\Support\Enums;

enum RateType: string
{
    case Hourly = 'hourly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Theo giờ',
            self::Monthly => 'Theo tháng',
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
