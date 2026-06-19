<?php

namespace App\Support\Enums;

/**
 * Phân loại dự án theo lĩnh vực: Phần cứng / Phần mềm.
 * Độc lập với vòng đời (ProjectType) và phạm vi (ProjectScope).
 * Dùng cho bộ chuyển Phần cứng/Phần mềm trên trang danh mục dự án.
 */
enum ProjectCategory: string
{
    case Hardware = 'hardware';
    case Software = 'software';

    public function label(): string
    {
        return match ($this) {
            self::Hardware => 'Phần cứng',
            self::Software => 'Phần mềm',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hardware => 'amber',
            self::Software => 'sky',
        };
    }

    /** Lucide icon key (see AppIcon.vue). */
    public function icon(): string
    {
        return match ($this) {
            self::Hardware => 'hardware',
            self::Software => 'software',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string, icon:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
            'icon' => $c->icon(),
        ], self::cases());
    }
}
