<?php

namespace App\Support\Enums;

enum BugStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case WontFix = 'wontfix';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Mới',
            self::InProgress => 'Đang sửa',
            self::InReview => 'Chờ kiểm tra',
            self::Resolved => 'Đã sửa',
            self::Closed => 'Đã đóng',
            self::Reopened => 'Mở lại',
            self::WontFix => 'Không sửa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::InProgress => 'sky',
            self::InReview => 'violet',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
            self::Reopened => 'amber',
            self::WontFix => 'slate',
        };
    }

    /** Whether the bug is considered finished (no longer active work). */
    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Closed, self::WontFix], true);
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
