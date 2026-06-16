<?php

namespace App\Support\Credential;

use App\Models\Credential;
use App\Models\SystemAccount;
use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use Illuminate\Database\Eloquent\Builder;

class CredentialSummaryBuilder
{
    /**
     * @return array<string, int|float|null>
     */
    public static function build(?SystemAccount $account = null): array
    {
        $base = Credential::query();
        if ($account) {
            $base = (clone $base)->visibleTo($account);
        }

        $expiringCutoff = now()->addDays(30);
        $providerTypes = [
            CredentialType::Provider->value,
        ];
        $providerCategories = [
            CredentialCategory::CloudProvider->value,
            CredentialCategory::HostingProvider->value,
            CredentialCategory::SmsProvider->value,
            CredentialCategory::EmailProvider->value,
            CredentialCategory::PaymentGateway->value,
            CredentialCategory::AiServices->value,
            CredentialCategory::ThirdPartyApi->value,
        ];

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', CredentialStatus::Active->value)->count(),
            'expiring_soon' => (clone $base)->where('status', CredentialStatus::Active->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff])
                ->count(),
            'locked' => (clone $base)->where('status', CredentialStatus::Locked->value)->count(),
            'shared' => (clone $base)->where('is_shared', true)->count(),
            'personal' => (clone $base)->where('is_shared', false)->count(),
            'no_owner' => (clone $base)->whereNull('owner_id')->count(),
            'domain_expiring' => (clone $base)->where('system_category', CredentialCategory::Domain->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff])
                ->count(),
            'ssl_expiring' => (clone $base)->where('system_category', CredentialCategory::Ssl->value)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), $expiringCutoff])
                ->count(),
            'vps_count' => (clone $base)->where('system_category', CredentialCategory::Vps->value)->count(),
            'database_count' => (clone $base)->where('system_category', CredentialCategory::Database->value)->count(),
            'provider_count' => (clone $base)->where(function (Builder $q) use ($providerTypes, $providerCategories) {
                $q->whereIn('credential_type', $providerTypes)
                    ->orWhereIn('system_category', $providerCategories);
            })->count(),
            'no_mfa' => (clone $base)->where('mfa_enabled', false)->count(),
            'password_overdue' => (clone $base)->whereNotNull('password_expires_at')
                ->where('password_expires_at', '<', now())
                ->count(),
        ];
    }
}
