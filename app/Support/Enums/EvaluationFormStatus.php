<?php

namespace App\Support\Enums;

enum EvaluationFormStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Active => 'Đang mở',
            self::Closed => 'Đã đóng',
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
