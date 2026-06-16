<?php

namespace App\Support\Enums;

enum CredentialAuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case ViewedPassword = 'viewed_password';
    case CopiedPassword = 'copied_password';
    case ChangedPassword = 'changed_password';
    case Shared = 'shared';
    case AccessGranted = 'access_granted';
    case AccessRevoked = 'access_revoked';

    public function labelVi(): string
    {
        return match ($this) {
            self::Created => 'Tạo mới',
            self::Updated => 'Chỉnh sửa',
            self::Deleted => 'Xóa',
            self::ViewedPassword => 'Xem mật khẩu',
            self::CopiedPassword => 'Sao chép mật khẩu',
            self::ChangedPassword => 'Đổi mật khẩu',
            self::Shared => 'Chia sẻ',
            self::AccessGranted => 'Cấp quyền',
            self::AccessRevoked => 'Thu hồi quyền',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
