<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAccessGrant extends Model
{
    protected $fillable = [
        'credential_id',
        'account_id',
        'permissions',
        'granted_by',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
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
