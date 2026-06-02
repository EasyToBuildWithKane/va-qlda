<?php

namespace App\Support\Enums;

enum ScoreTrend: string
{
    case Up = 'up';
    case Stable = 'stable';
    case Down = 'down';

    public function symbol(): string
    {
        return match ($this) {
            self::Up => 'up',
            self::Stable => 'stable',
            self::Down => 'down',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Tăng',
            self::Stable => 'Ổn định',
            self::Down => 'Giảm',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up => 'emerald',
            self::Stable => 'slate',
            self::Down => 'rose',
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
