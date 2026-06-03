<?php

namespace App\Support\Enums;

enum AiAccountGroupFunction: string
{
    case Dev = 'DEV';
    case Ba = 'BA';
    case Pm = 'PM';
    case Design = 'Design';
    case Qa = 'QA';
    case Other = 'Other';

    public function label(): string
    {
        return $this->value;
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Dev => '#185FA5',
            self::Ba => '#854F0B',
            self::Pm => '#993556',
            self::Design => '#534AB7',
            self::Qa => '#3B6D11',
            self::Other => '#5F5E5A',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, dot_color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'dot_color' => $c->dotColor(),
        ], self::cases());
    }

    /** @return array<int, self> */
    public static function ordered(): array
    {
        return [
            self::Dev,
            self::Ba,
            self::Pm,
            self::Design,
            self::Qa,
            self::Other,
        ];
    }
}
