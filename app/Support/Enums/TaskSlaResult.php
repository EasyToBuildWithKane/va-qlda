<?php

namespace App\Support\Enums;

enum TaskSlaResult: string
{
    case Met = 'met';
    case Exceeded = 'exceeded';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'Đạt SLA',
            self::Exceeded => 'Vượt SLA',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Met => 'emerald',
            self::Exceeded => 'rose',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
