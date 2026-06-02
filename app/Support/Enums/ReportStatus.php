<?php

namespace App\Support\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Reviewed => 'Reviewed',
        };
    }

    /**
     * Tailwind-ish token used by the StatusBadge component.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'amber',
            self::Reviewed => 'emerald',
        };
    }
}
