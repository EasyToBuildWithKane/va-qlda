<?php

namespace App\Support;

use App\Models\SecurityAuditLog;
use App\Models\SystemAccount;

class SecurityAuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        ?SystemAccount $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
    ): void {
        SecurityAuditLog::create([
            'actor_account_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }

    public static function aiAccountPasswordViewed(SystemAccount $actor, int $aiAccountId, string $label): void
    {
        self::log(
            $actor,
            'ai_account.password_viewed',
            'ai_account',
            $aiAccountId,
            ['label' => $label],
        );
    }
}
