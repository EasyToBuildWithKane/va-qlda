<?php

namespace App\Support\Enums;

enum BlockerStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::InProgress => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::InProgress => 'amber',
            self::Resolved => 'emerald',
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
