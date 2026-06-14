<?php

namespace App\Support\Enums;

/**
 * Derived (not stored) — computed from a certification's expiry date.
 */
enum CertificationStatus: string
{
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Còn hiệu lực',
            self::Expiring => 'Sắp hết hạn',
            self::Expired => 'Đã hết hạn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'emerald',
            self::Expiring => 'amber',
            self::Expired => 'rose',
        };
    }
}
