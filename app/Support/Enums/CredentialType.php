<?php

namespace App\Support\Enums;

enum CredentialType: string
{
    case InternalSystem = 'internal_system';
    case Infrastructure = 'infrastructure';
    case Provider = 'provider';
    case WorkingAccount = 'working_account';

    public function labelVi(): string
    {
        return match ($this) {
            self::InternalSystem => 'Hệ thống nội bộ',
            self::Infrastructure => 'Hạ tầng kỹ thuật',
            self::Provider => 'Nhà cung cấp',
            self::WorkingAccount => 'Tài khoản làm việc',
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
