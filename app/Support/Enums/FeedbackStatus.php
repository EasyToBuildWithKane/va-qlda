<?php

namespace App\Support\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::UnderReview => 'Đang xem xét',
            self::Planned => 'Đã lên kế hoạch',
            self::InProgress => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
            self::Declined => 'Từ chối',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::UnderReview => 'violet',
            self::Planned => 'amber',
            self::InProgress => 'sky',
            self::Resolved => 'emerald',
            self::Declined => 'slate',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Declined], true);
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
