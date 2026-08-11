<?php

namespace App\Support\Enums;

enum AiAccountStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case OutOfToken = 'out_of_token';
    case Cancelled = 'cancelled';

    public function labelVi(): string
    {
        return match ($this) {
            self::Active => 'Đang sử dụng',
            self::ExpiringSoon => 'Sắp hết hạn',
            self::Expired => 'Hết hạn',
            self::OutOfToken => 'Hết token',
            self::Cancelled => 'Không còn sử dụng',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::ExpiringSoon => 'amber',
            self::Expired => 'rose',
            self::OutOfToken => 'violet',
            self::Cancelled => 'slate',
        };
    }

    /** Trạng thái người dùng chọn tay — không bị sync ngày hết hạn ghi đè. */
    public function isManualLock(): bool
    {
        return in_array($this, [self::OutOfToken, self::Cancelled], true);
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

    /**
     * 3 trạng thái vận hành chính trên form / segmented.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function usageOptions(): array
    {
        return [
            ['value' => self::Active->value, 'label' => self::Active->labelVi()],
            ['value' => self::OutOfToken->value, 'label' => self::OutOfToken->labelVi()],
            ['value' => self::Cancelled->value, 'label' => self::Cancelled->labelVi()],
        ];
    }
}
