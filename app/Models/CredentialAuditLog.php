<?php

namespace App\Models;

use App\Support\Enums\CredentialAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'credential_id',
        'account_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'action' => CredentialAuditAction::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'account_id');
    }
}
