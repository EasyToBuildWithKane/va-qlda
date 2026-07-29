<?php

namespace App\Support\Enums;

enum EvaluationTemplateType: string
{
    case PointSystem = 'point_system';
    case Scorecard = 'scorecard';

    public function label(): string
    {
        return match ($this) {
            self::PointSystem => 'Điểm cộng / trừ',
            self::Scorecard => 'Phiếu tiêu chí',
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
