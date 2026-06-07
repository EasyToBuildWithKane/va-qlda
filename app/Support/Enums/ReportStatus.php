<?php

namespace App\Support\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Submitted => 'Chờ duyệt',
            self::Reviewed => 'Đã duyệt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'amber',
            self::Reviewed => 'emerald',
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
