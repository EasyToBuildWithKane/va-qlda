<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAccountPasswordViewer extends Model
{
    protected $fillable = [
        'ai_account_id',
        'system_account_id',
        'granted_by',
    ];

    public function aiAccount(): BelongsTo
    {
        return $this->belongsTo(AiAccount::class);
    }

    public function systemAccount(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'system_account_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'granted_by');
    }

    public static function canViewPassword(SystemAccount $viewer, AiAccount $aiAccount): bool
    {
        if ($viewer->isAdminTier()) {
            return true;
        }

        return static::query()
            ->where('ai_account_id', $aiAccount->id)
            ->where('system_account_id', $viewer->id)
            ->exists();
    }
}
