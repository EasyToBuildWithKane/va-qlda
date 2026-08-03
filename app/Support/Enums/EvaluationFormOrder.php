<?php

namespace App\Support\Enums;

enum EvaluationFormOrder: string
{
    case Parallel = 'parallel';
    case Sequential = 'sequential';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Parallel => 'Đánh giá song song',
            self::Sequential => 'Đánh giá tuần tự',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
