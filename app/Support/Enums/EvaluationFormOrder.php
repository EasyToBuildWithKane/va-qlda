<?php

namespace App\Support\Enums;

enum EvaluationFormOrder: string
{
    case Parallel = 'parallel';
    case Sequential = 'sequential';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Parallel => 'Đánh giá song song',
            self::Sequential => 'Đánh giá tuần tự',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Parallel => 'Thành viên có thể đánh giá song song',
            self::Sequential => 'Bạn có thể kéo thả danh sách ở dưới để thay đổi thứ tự',
        };
    }

    /** @return list<array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ],
            self::cases(),
        );
    }
}
