<?php

namespace App\Support\Enums;

enum AiPurchaseType: string
{
    case New = 'new';
    case Renewal = 'renewal';

    public function labelVi(): string
    {
        return match ($this) {
            self::New => 'Mua mới',
            self::Renewal => 'Gia hạn',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
