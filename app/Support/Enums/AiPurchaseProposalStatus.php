<?php

namespace App\Support\Enums;

enum AiPurchaseProposalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelVi(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
