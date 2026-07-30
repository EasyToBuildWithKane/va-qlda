<?php

namespace App\Support\Enums;

enum EvaluationCriterionScope: string
{
    case General = 'general';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Tiêu chí chung',
            self::Department => 'Theo phòng ban',
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
