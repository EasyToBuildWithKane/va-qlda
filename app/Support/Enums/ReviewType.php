<?php

namespace App\Support\Enums;

enum ReviewType: string
{
    case SelfReview = 'self';
    case Manager = 'manager';
    case Peer = 'peer';

    public function label(): string
    {
        return match ($this) {
            self::SelfReview => 'Tự đánh giá',
            self::Manager => 'Quản lý đánh giá',
            self::Peer => 'Đồng nghiệp đánh giá',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SelfReview => 'sky',
            self::Manager => 'brand',
            self::Peer => 'violet',
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
