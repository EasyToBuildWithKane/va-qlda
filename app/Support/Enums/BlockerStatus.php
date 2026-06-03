<?php

namespace App\Support\Enums;

enum BlockerStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return $this->labelVi();
    }

    public function labelVi(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::InProgress => 'Đang xử lý',
            self::Blocked => 'Bị chặn',
            self::Resolved => 'Đã giải quyết',
            self::Closed => 'Đã đóng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::InProgress => 'sky',
            self::Blocked => 'violet',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
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
            'label' => $c->labelVi(),
            'label_en' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
