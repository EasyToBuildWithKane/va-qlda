<?php

namespace App\Support\Enums;

enum CredentialRelationType: string
{
    case RunsOn = 'runs_on';
    case UsesDatabase = 'uses_database';
    case UsesDns = 'uses_dns';
    case SecuredBySsl = 'secured_by_ssl';
    case HostedBy = 'hosted_by';
    case Related = 'related';

    public function labelVi(): string
    {
        return match ($this) {
            self::RunsOn => 'Chạy trên',
            self::UsesDatabase => 'Dùng database',
            self::UsesDns => 'Dùng DNS',
            self::SecuredBySsl => 'Bảo mật SSL',
            self::HostedBy => 'Hosted bởi',
            self::Related => 'Liên quan',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
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
