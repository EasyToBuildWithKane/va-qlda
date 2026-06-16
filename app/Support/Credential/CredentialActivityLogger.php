<?php

namespace App\Support\Credential;

use App\Models\Credential;
use App\Models\CredentialAuditLog;
use App\Models\SystemAccount;
use App\Support\Enums\CredentialAuditAction;
use Illuminate\Http\Request;

class CredentialActivityLogger
{
    public static function log(
        Credential $credential,
        CredentialAuditAction $action,
        ?SystemAccount $account,
        ?array $metadata = null,
        ?Request $request = null,
    ): void {
        CredentialAuditLog::create([
            'credential_id' => $credential->id,
            'account_id' => $account?->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 512) : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public static function created(Credential $credential, SystemAccount $account, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::Created, $account, ['name' => $credential->name], $request);
    }

    public static function updated(Credential $credential, SystemAccount $account, array $changes, ?Request $request = null): void
    {
        if ($changes === []) {
            return;
        }

        self::log($credential, CredentialAuditAction::Updated, $account, ['fields' => array_keys($changes)], $request);
    }

    public static function deleted(Credential $credential, SystemAccount $account, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::Deleted, $account, null, $request);
    }

    public static function viewedPassword(Credential $credential, SystemAccount $account, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::ViewedPassword, $account, null, $request);
    }

    public static function copiedPassword(Credential $credential, SystemAccount $account, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::CopiedPassword, $account, null, $request);
    }

    public static function changedPassword(Credential $credential, SystemAccount $account, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::ChangedPassword, $account, null, $request);
    }

    public static function accessGranted(Credential $credential, SystemAccount $account, int $targetAccountId, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::AccessGranted, $account, ['account_id' => $targetAccountId], $request);
    }

    public static function accessRevoked(Credential $credential, SystemAccount $account, int $targetAccountId, ?Request $request = null): void
    {
        self::log($credential, CredentialAuditAction::AccessRevoked, $account, ['account_id' => $targetAccountId], $request);
    }
}
