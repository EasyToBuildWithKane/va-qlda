<?php

namespace App\Support\Enums;

enum TestCasePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return $this->labelVi();
    }

    public function labelVi(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Medium => 'Trung bình',
            self::High => 'Cao',
            self::Critical => 'Nghiêm trọng',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Medium => 'sky',
            self::High => 'amber',
            self::Critical => 'rose',
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
