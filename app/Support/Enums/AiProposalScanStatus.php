<?php

namespace App\Support\Enums;

enum AiProposalScanStatus: string
{
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function labelVi(): string
    {
        return match ($this) {
            self::Processing => 'Đang xử lý',
            self::NeedsReview => 'Chờ kiểm tra',
            self::Confirmed => 'Đã xác nhận',
            self::Failed => 'Thất bại',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Processing => 'sky',
            self::NeedsReview => 'amber',
            self::Confirmed => 'emerald',
            self::Failed => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
