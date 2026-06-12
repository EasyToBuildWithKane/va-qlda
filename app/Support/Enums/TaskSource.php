<?php

namespace App\Support\Enums;

enum TaskSource: string
{
    case Sprint = 'sprint';
    case Daily = 'daily';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
