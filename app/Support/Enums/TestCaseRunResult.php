<?php

namespace App\Support\Enums;

enum TestCaseRunResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Blocked = 'blocked';
    case Skipped = 'skipped';

    public function label(): string
    {
        return $this->labelVi();
    }

    public function labelVi(): string
    {
        return match ($this) {
            self::Pass => 'Đạt',
            self::Fail => 'Không đạt',
            self::Blocked => 'Bị chặn',
            self::Skipped => 'Bỏ qua',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pass => 'emerald',
            self::Fail => 'rose',
            self::Blocked => 'violet',
            self::Skipped => 'slate',
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
