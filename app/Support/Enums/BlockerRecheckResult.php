<?php

namespace App\Support\Enums;

enum BlockerRecheckResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function labelVi(): string
    {
        return match ($this) {
            self::Pending => 'Chờ kiểm tra',
            self::Passed => 'Đạt',
            self::Failed => 'Không đạt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Passed => 'emerald',
            self::Failed => 'rose',
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
