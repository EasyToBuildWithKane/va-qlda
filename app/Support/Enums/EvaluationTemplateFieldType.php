<?php

namespace App\Support\Enums;

enum EvaluationTemplateFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Date = 'date';
    case Checkbox = 'checkbox';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Văn bản ngắn',
            self::Textarea => 'Văn bản dài',
            self::Number => 'Số',
            self::Select => 'Danh sách chọn',
            self::Date => 'Ngày',
            self::Checkbox => 'Checkbox',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
