<?php

namespace App\Support\Enums;

enum ProposalType: string
{
    case AiAccount = 'ai_account';
    case Software = 'software';
    case SaaS = 'saas';
    case License = 'license';
    case Hardware = 'hardware';
    case Equipment = 'equipment';
    case Service = 'service';
    case Other = 'other';

    public function labelVi(): string
    {
        return match ($this) {
            self::AiAccount => 'AI Account',
            self::Software => 'Phần mềm',
            self::SaaS => 'SaaS',
            self::License => 'License',
            self::Hardware => 'Hardware',
            self::Equipment => 'Thiết bị',
            self::Service => 'Dịch vụ',
            self::Other => 'Khác',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AiAccount => 'ai',
            self::Software => 'code',
            self::SaaS => 'cloud',
            self::License => 'key',
            self::Hardware => 'server',
            self::Equipment => 'device',
            self::Service => 'service',
            self::Other => 'other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->labelVi(),
        ], self::cases());
    }
}
