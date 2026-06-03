<?php

namespace App\Support\Enums;

enum AiAccountStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function labelVi(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::ExpiringSoon => 'Sắp hết hạn',
            self::Expired => 'Hết hạn',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::ExpiringSoon => 'amber',
            self::Expired => 'rose',
            self::Cancelled => 'slate',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
