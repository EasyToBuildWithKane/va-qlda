<?php

namespace App\Support\Enums;

/**
 * Đề xuất sau đánh giá nhà cung cấp (cột "Đề xuất" của sheet Reviews):
 * tiếp tục gia hạn / không gia hạn / đổi NCC / cần review thêm.
 */
enum ContractReviewRecommendation: string
{
    case Renew = 'renew';
    case DoNotRenew = 'do_not_renew';
    case ChangeVendor = 'change_vendor';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Renew => 'Tiếp tục gia hạn',
            self::DoNotRenew => 'Không gia hạn',
            self::ChangeVendor => 'Thay đổi NCC',
            self::NeedsReview => 'Cần review',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Renew => 'emerald',
            self::DoNotRenew => 'rose',
            self::ChangeVendor => 'amber',
            self::NeedsReview => 'slate',
        };
    }

    /** Đề xuất cảnh báo (không nên gia hạn như cũ). */
    public function isAlert(): bool
    {
        return in_array($this, [self::DoNotRenew, self::ChangeVendor], true);
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
