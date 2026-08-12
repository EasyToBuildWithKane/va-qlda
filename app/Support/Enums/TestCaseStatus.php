<?php

namespace App\Support\Enums;

enum TestCaseStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Deprecated = 'deprecated';

    public function label(): string
    {
        return $this->labelVi();
    }

    public function labelVi(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Ready => 'Sẵn sàng',
            self::Deprecated => 'Không còn dùng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Ready => 'emerald',
            self::Deprecated => 'amber',
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
            'label' => $c->labelVi(),
            'color' => $c->color(),
        ], self::cases());
    }
}
