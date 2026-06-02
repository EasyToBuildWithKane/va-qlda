<?php

namespace App\Support\Enums;

/**
 * Vòng đời dự án: nghiên cứu phát triển → triển khai nghiệm thu → vận hành cải tiến.
 * Dùng làm cột Kanban trên trang danh mục dự án.
 */
enum ProjectType: string
{
    case Rnd = 'rnd';
    case Deployment = 'deployment';
    case Operation = 'operation';

    public function label(): string
    {
        return match ($this) {
            self::Rnd => 'Nghiên cứu phát triển',
            self::Deployment => 'Triển khai nghiệm thu',
            self::Operation => 'Vận hành cải tiến',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rnd => 'violet',
            self::Deployment => 'amber',
            self::Operation => 'emerald',
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
