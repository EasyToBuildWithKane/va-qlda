<?php

namespace App\Support\Enums;

enum TaskPhase: string
{
    case Discovery = 'discovery';
    case Analysis = 'analysis';
    case Design = 'design';
    case Development = 'development';
    case Testing = 'testing';
    case Uat = 'uat';
    case Deployment = 'deployment';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Discovery => 'Discovery',
            self::Analysis => 'Analysis',
            self::Design => 'Design',
            self::Development => 'Development',
            self::Testing => 'Testing',
            self::Uat => 'UAT',
            self::Deployment => 'Deployment',
            self::Maintenance => 'Maintenance',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ], self::cases());
    }
}
