<?php

namespace App\Support\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Dự kiến',
            self::Active => 'Đang chạy',
            self::Completed => 'Đã đóng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'slate',
            self::Active => 'sky',
            self::Completed => 'emerald',
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
