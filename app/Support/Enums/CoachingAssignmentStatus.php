<?php

namespace App\Support\Enums;

enum CoachingAssignmentStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Review = 'review';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Cần làm',
            self::Doing => 'Đang làm',
            self::Review => 'Chờ duyệt',
            self::Done => 'Hoàn thành',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
