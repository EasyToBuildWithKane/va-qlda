<?php

namespace App\Support\Enums;

/**
 * Trạng thái hợp tác nhà cung cấp (CLM).
 * `is_active` trên vendors được đồng bộ: false chỉ khi Inactive.
 */
enum VendorCooperationStatus: string
{
    case Active = 'active';
    case Potential = 'potential';
    case Research = 'research';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hợp tác',
            self::Potential => 'Tiềm năng',
            self::Research => 'Nghiên cứu',
            self::Inactive => 'Ngừng hợp tác',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Potential => 'sky',
            self::Research => 'violet',
            self::Inactive => 'slate',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Active => 'Đang có quan hệ hợp tác / cung cấp dịch vụ.',
            self::Potential => 'NCC tiềm năng — chưa ký hoặc đang xem xét.',
            self::Research => 'Đang nghiên cứu, khảo sát thị trường.',
            self::Inactive => 'Ngừng hợp tác — vẫn giữ lịch sử hợp đồng và đánh giá.',
        };
    }

    public function isActiveFlag(): bool
    {
        return $this !== self::Inactive;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return list<array{value: string, label: string, color: string, hint: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
            'hint' => $c->hint(),
        ], self::cases());
    }

    public static function tryFromLabel(?string $raw): ?self
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $byValue = self::tryFrom(mb_strtolower($raw));
        if ($byValue) {
            return $byValue;
        }

        $n = self::normalizeKey($raw);

        return match (true) {
            str_contains($n, 'tiem nang') || $n === 'potential' => self::Potential,
            str_contains($n, 'nghien cuu') || $n === 'research' => self::Research,
            str_contains($n, 'ngung')
                || $n === 'inactive'
                || $n === '0'
                || $n === 'false'
                || $n === 'khong' => self::Inactive,
            str_contains($n, 'hop tac')
                || str_contains($n, 'hoat dong')
                || $n === 'active'
                || $n === '1'
                || $n === 'true'
                || $n === 'co' => self::Active,
            default => null,
        };
    }

    private static function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['đ', 'Đ'], ['d', 'd'], $value);
        $value = \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value;
        $value = preg_replace('/\p{Mn}/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
