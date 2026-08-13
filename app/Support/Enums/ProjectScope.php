<?php

namespace App\Support\Enums;

/**
 * Phạm vi áp dụng của dự án: Hội sở / Toàn hệ thống / Khu vực / Phòng ban.
 * Quyết định dự án được triển khai ở đâu (độc lập với phòng ban phụ trách và phòng liên đới).
 */
enum ProjectScope: string
{
    case Headquarters = 'headquarters';
    case System = 'system';
    case Regional = 'regional';
    case Departmental = 'departmental';

    public function label(): string
    {
        return match ($this) {
            self::Headquarters => 'Hội sở',
            self::System => 'Toàn hệ thống',
            self::Regional => 'Khu vực',
            self::Departmental => 'Phòng ban',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Headquarters => 'Chỉ triển khai tại văn phòng hội sở.',
            self::System => 'Áp dụng cho toàn bộ hệ thống VA School.',
            self::Regional => 'Triển khai theo từng khu vực/chi nhánh.',
            self::Departmental => 'Áp dụng cho các phòng ban liên đới được chọn bên dưới.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Headquarters => 'brand',
            self::System => 'violet',
            self::Regional => 'amber',
            self::Departmental => 'cyan',
        };
    }

    /** Lucide icon key (see AppIcon.vue) for the radio-card UI. */
    public function icon(): string
    {
        return match ($this) {
            self::Headquarters => 'building',
            self::System => 'globe',
            self::Regional => 'map-pin',
            self::Departmental => 'department',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, description:string, color:string, icon:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
            'color' => $c->color(),
            'icon' => $c->icon(),
        ], self::cases());
    }
}
