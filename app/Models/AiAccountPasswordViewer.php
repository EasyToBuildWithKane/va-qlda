<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAccountPasswordViewer extends Model
{
    protected $fillable = [
        'system_account_id',
        'granted_by',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'system_account_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'granted_by');
    }

    public static function canAccountViewPassword(SystemAccount $account): bool
    {
        if ($account->role === \App\Support\Enums\SystemRole::Admin) {
            return true;
        }

        return static::query()->where('system_account_id', $account->id)->exists();
    }
}
