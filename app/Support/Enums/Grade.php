<?php

namespace App\Support\Enums;

enum Grade: string
{
    case S = 'S';
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    /**
     * Resolve a grade from a total score using the configured thresholds.
     */
    public static function fromScore(float $total): self
    {
        foreach (config('daily_report.grades') as $grade => $min) {
            if ($total >= $min) {
                return self::from($grade);
            }
        }

        return self::D;
    }

    public function label(): string
    {
        return match ($this) {
            self::S => 'S — Xuất sắc',
            self::A => 'A — Tốt',
            self::B => 'B — Khá',
            self::C => 'C — Trung bình',
            self::D => 'D — Yếu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::S => 'emerald',
            self::A => 'green',
            self::B => 'sky',
            self::C => 'amber',
            self::D => 'rose',
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
