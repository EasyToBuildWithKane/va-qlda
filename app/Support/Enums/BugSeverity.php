<?php

namespace App\Support\Enums;

enum BugSeverity: string
{
    case Trivial = 'trivial';
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
    case Blocker = 'blocker';

    public function label(): string
    {
        return match ($this) {
            self::Trivial => 'Không đáng kể',
            self::Minor => 'Nhẹ',
            self::Major => 'Nghiêm trọng',
            self::Critical => 'Rất nghiêm trọng',
            self::Blocker => 'Chặn đứng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trivial => 'slate',
            self::Minor => 'sky',
            self::Major => 'amber',
            self::Critical => 'rose',
            self::Blocker => 'rose',
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
