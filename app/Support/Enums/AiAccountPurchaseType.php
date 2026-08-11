<?php

namespace App\Support\Enums;

enum AiAccountPurchaseType: string
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
}
