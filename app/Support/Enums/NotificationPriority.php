<?php

namespace App\Support\Enums;

enum NotificationPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Khẩn cấp',
            self::High => 'Cao',
            self::Medium => 'Trung bình',
            self::Low => 'Thấp',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'rose',
            self::High => 'orange',
            self::Medium => 'amber',
            self::Low => 'slate',
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
