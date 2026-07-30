<?php

namespace App\Support\Enums;

enum EvaluationScoringType: string
{
    case Scale = 'scale';
    case Points = 'points';

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'Thang nhãn 1–5',
            self::Points => 'Điểm cộng / trừ',
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
