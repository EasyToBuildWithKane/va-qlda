<?php

namespace App\Support\Enums;

enum AiPurchaseProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Purchased = 'purchased';
    case Active = 'active';
    case Expired = 'expired';

    public function labelVi(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Submitted => 'Đã gửi',
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
            self::Purchased => 'Đã mua',
            self::Active => 'Đang sử dụng',
            self::Expired => 'Hết hạn',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'blue',
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
            self::Purchased => 'violet',
            self::Active => 'teal',
            self::Expired => 'slate',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Expired], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Submitted,
            self::Submitted => $next === self::Pending,
            self::Pending => in_array($next, [self::Approved, self::Rejected], true),
            self::Approved => $next === self::Purchased,
            self::Purchased => $next === self::Active,
            self::Active => $next === self::Expired,
            default => false,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
            'color' => $c->badgeColor(),
        ], self::cases());
    }
}
