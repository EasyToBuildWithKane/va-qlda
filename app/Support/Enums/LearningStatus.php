<?php

namespace App\Support\Enums;

enum LearningStatus: string
{
    case Completed = 'completed';
    case InProgress = 'in_progress';
    case Recommended = 'recommended';
    case Planned = 'planned';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Đã hoàn thành',
            self::InProgress => 'Đang học',
            self::Recommended => 'Đề xuất',
            self::Planned => 'Dự kiến',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'emerald',
            self::InProgress => 'sky',
            self::Recommended => 'amber',
            self::Planned => 'slate',
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
