<?php

namespace App\Support\Enums;

/**
 * Vòng đời hợp đồng. `expiring_soon` / `expired` được đồng bộ tự động bởi
 * command `contracts:send-reminders` dựa trên `expiry_date`, không nhập tay,
 * để Explorer & Dashboard luôn nhất quán.
 */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case PendingRenewal = 'pending_renewal';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Active => 'Đang hiệu lực',
            self::ExpiringSoon => 'Sắp hết hạn',
            self::Expired => 'Đã hết hạn',
            self::PendingRenewal => 'Chờ gia hạn',
            self::Terminated => 'Đã thanh lý',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Active => 'emerald',
            self::ExpiringSoon => 'amber',
            self::Expired => 'rose',
            self::PendingRenewal => 'violet',
            self::Terminated => 'slate',
        };
    }

    /** Trạng thái còn "sống" (cần theo dõi gia hạn). */
    public function isLive(): bool
    {
        return in_array($this, [self::Active, self::ExpiringSoon, self::PendingRenewal], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Terminated], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
