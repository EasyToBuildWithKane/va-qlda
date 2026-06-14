<?php

namespace App\Support\Enums;

enum SuccessionReadiness: string
{
    case NotReady = 'not_ready';
    case Potential = 'potential';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::NotReady => 'Chưa sẵn sàng',
            self::Potential => 'Tiềm năng',
            self::Ready => 'Sẵn sàng thăng tiến',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotReady => 'slate',
            self::Potential => 'amber',
            self::Ready => 'emerald',
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
