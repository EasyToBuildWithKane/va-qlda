<?php

namespace App\Support\Enums;

enum AiAccountLoginMethod: string
{
    case Password = 'password';
    case Google = 'google';

    public function labelVi(): string
    {
        return match ($this) {
            self::Password => 'Tài khoản thường',
            self::Google => 'Google',
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
