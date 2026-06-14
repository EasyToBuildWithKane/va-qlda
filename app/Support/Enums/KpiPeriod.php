<?php

namespace App\Support\Enums;

enum KpiPeriod: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Tháng',
            self::Quarter => 'Quý',
            self::Year => 'Năm',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Month => 'sky',
            self::Quarter => 'violet',
            self::Year => 'brand',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
