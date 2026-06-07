<?php

namespace App\Support\Enums;

enum AiAccountLifecycleStatus: string
{
    case NotPurchased = 'not_purchased';
    case Purchased = 'purchased';
    case Allocated = 'allocated';
    case InUse = 'in_use';
    case Expired = 'expired';
    case Stopped = 'stopped';

    public function labelVi(): string
    {
        return match ($this) {
            self::NotPurchased => 'Chưa mua',
            self::Purchased => 'Đã mua',
            self::Allocated => 'Đã cấp phát',
            self::InUse => 'Đang sử dụng',
            self::Expired => 'Hết hạn',
            self::Stopped => 'Ngừng sử dụng',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::NotPurchased => 'slate',
            self::Purchased => 'violet',
            self::Allocated => 'blue',
            self::InUse => 'emerald',
            self::Expired => 'rose',
            self::Stopped => 'slate',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
            'color' => $c->badgeColor(),
        ], self::cases());
    }
}
