<?php

namespace App\Support\Enums;

enum TaskHoursTiming: string
{
    case Early = 'early';
    case OnPlan = 'on_plan';
    case OverPlan = 'over_plan';

    public function label(): string
    {
        return match ($this) {
            self::Early => 'Sớm hơn dự kiến',
            self::OnPlan => 'Đúng kế hoạch',
            self::OverPlan => 'Vượt thời gian dự kiến',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Early => 'emerald',
            self::OnPlan => 'amber',
            self::OverPlan => 'rose',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
