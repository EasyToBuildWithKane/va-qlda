<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAccountAccessGrant extends Model
{
    protected $fillable = [
        'ai_account_id',
        'account_id',
        'permissions',
        'granted_by',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
    ];

    public function aiAccount(): BelongsTo
    {
        return $this->belongsTo(AiAccount::class, 'ai_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'account_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'granted_by');
    }
}
