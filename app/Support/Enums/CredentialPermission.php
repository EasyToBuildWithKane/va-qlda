<?php

namespace App\Support\Enums;

enum CredentialPermission: string
{
    case View = 'view';
    case CopyPassword = 'copy_password';
    case Edit = 'edit';
    case Delete = 'delete';
    case Export = 'export';
    case Share = 'share';

    public function labelVi(): string
    {
        return match ($this) {
            self::View => 'Xem',
            self::CopyPassword => 'Sao chép mật khẩu',
            self::Edit => 'Chỉnh sửa',
            self::Delete => 'Xóa',
            self::Export => 'Xuất',
            self::Share => 'Chia sẻ',
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
