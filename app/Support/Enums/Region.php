<?php

namespace App\Support\Enums;

/**
 * Khu vực triển khai (dùng khi phạm vi áp dụng = Khu vực).
 */
enum Region: string
{
    case Saigon = 'saigon';
    case VungTau = 'vungtau';
    case CanTho = 'cantho';
    case DongNai = 'dongnai';
    case BinhDuong = 'binhduong';

    public function label(): string
    {
        return match ($this) {
            self::Saigon => 'Sài Gòn',
            self::VungTau => 'Vũng Tàu',
            self::CanTho => 'Cần Thơ',
            self::DongNai => 'Đồng Nai',
            self::BinhDuong => 'Bình Dương',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** Map a stored value to its Vietnamese label (null-safe). */
    public static function labelFor(?string $value): ?string
    {
        $case = $value !== null ? self::tryFrom($value) : null;

        return $case?->label();
    }

    /** @return array<int, array{value:string, label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ], self::cases());
    }
}
