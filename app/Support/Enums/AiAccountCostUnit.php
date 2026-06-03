<?php

namespace App\Support\Enums;

enum AiAccountCostUnit: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case OneTime = 'one_time';

    public function labelVi(): string
    {
        return match ($this) {
            self::Monthly => 'tháng',
            self::Yearly => 'năm',
            self::OneTime => 'một lần',
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
            'label' => match ($c) {
                self::Monthly => 'Hàng tháng',
                self::Yearly => 'Hàng năm',
                self::OneTime => 'Một lần',
            },
        ], self::cases());
    }
}
