<?php

namespace App\Support\Enums;

enum CongngheSoftwareProposalStatus: string
{
    case New = 'new';
    case Triaged = 'triaged';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Triaged => 'Đã tiếp nhận',
            self::InProgress => 'Đang xử lý',
            self::Done => 'Hoàn thành',
            self::Rejected => 'Từ chối',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
