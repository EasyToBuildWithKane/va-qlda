<?php

namespace App\Support\Enums;

enum CredentialStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case Locked = 'locked';

    public function labelVi(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Inactive => 'Ngưng dùng',
            self::Expired => 'Hết hạn',
            self::Locked => 'Bị khóa',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Inactive => 'slate',
            self::Expired => 'amber',
            self::Locked => 'rose',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
        ], self::cases());
    }
}
