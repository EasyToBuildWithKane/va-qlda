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
}
