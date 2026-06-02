<?php

namespace App\Support\Enums;

enum NotificationCategory: string
{
    case Task = 'task';
    case Sprint = 'sprint';
    case Project = 'project';
    case Document = 'document';
    case Comment = 'comment';
    case System = 'system';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Công việc',
            self::Sprint => 'Sprint',
            self::Project => 'Dự án',
            self::Document => 'Tài liệu',
            self::Comment => 'Bình luận',
            self::System => 'Hệ thống',
            self::Admin => 'Quản trị',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Task => 'sky',
            self::Sprint => 'violet',
            self::Project => 'indigo',
            self::Document => 'amber',
            self::Comment => 'teal',
            self::System => 'slate',
            self::Admin => 'rose',
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
