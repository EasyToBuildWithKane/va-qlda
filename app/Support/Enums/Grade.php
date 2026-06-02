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
}
