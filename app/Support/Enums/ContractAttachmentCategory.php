<?php

namespace App\Support\Enums;

/**
 * Loại hồ sơ trong kho tài liệu hợp đồng (Document Management).
 */
enum ContractAttachmentCategory: string
{
    case Contract = 'contract';
    case Appendix = 'appendix';
    case Quote = 'quote';
    case Acceptance = 'acceptance';
    case Liquidation = 'liquidation';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Hợp đồng',
            self::Appendix => 'Phụ lục',
            self::Quote => 'Báo giá',
            self::Acceptance => 'Nghiệm thu',
            self::Liquidation => 'Thanh lý',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Contract => 'brand',
            self::Appendix => 'sky',
            self::Quote => 'amber',
            self::Acceptance => 'emerald',
            self::Liquidation => 'slate',
        };
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
