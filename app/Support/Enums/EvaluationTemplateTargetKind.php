<?php

namespace App\Support\Enums;

enum EvaluationTemplateTargetKind: string
{
    case Title = 'title';
    case Rank = 'rank';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Chức danh',
            self::Rank => 'Cấp bậc',
        };
    }
}
