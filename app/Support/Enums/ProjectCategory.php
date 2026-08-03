<?php

namespace App\Support\Enums;

/**
 * Phân loại dự án theo lĩnh vực: Phần cứng / Phần mềm (legacy).
 * Độc lập với vòng đời (ProjectType) và phạm vi (ProjectScope).
 * Không còn hiện trên form tạo/sửa hay chip lọc Index — cột danh sách vẫn hỗ trợ dữ liệu cũ.
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
