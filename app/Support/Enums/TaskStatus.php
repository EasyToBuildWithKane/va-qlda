<?php

namespace App\Support\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Cần làm',
            self::InProgress => 'Đang làm',
            self::InReview => 'Đang review',
            self::Done => 'Hoàn thành',
            self::Blocked => 'Bị chặn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'slate',
            self::InProgress => 'sky',
            self::InReview => 'violet',
            self::Done => 'emerald',
            self::Blocked => 'rose',
        };
    }

    /** Statuses shown as Kanban columns, in order. */
    public static function board(): array
    {
        return [self::Todo, self::InProgress, self::InReview, self::Done];
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
