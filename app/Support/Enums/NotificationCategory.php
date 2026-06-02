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
}
