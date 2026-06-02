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
}
