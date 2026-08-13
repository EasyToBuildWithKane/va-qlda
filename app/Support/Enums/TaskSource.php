<?php

namespace App\Support\Enums;

enum TaskSource: string
{
    case Sprint = 'sprint';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Sprint => 'Từ sprint',
            self::Daily => 'Từ báo cáo ngày',
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
            'label' => $c->label(),
        ], self::cases());
    }
}
