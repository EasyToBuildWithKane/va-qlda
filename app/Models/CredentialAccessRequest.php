<?php

namespace App\Models;

use App\Support\Enums\CredentialAccessRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAccessRequest extends Model
{
    protected $fillable = [
        'credential_id',
        'requester_id',
        'approver_id',
        'status',
        'requested_permissions',
        'reason',
        'responded_at',
        'expires_at',
    ];

    protected $casts = [
        'status' => CredentialAccessRequestStatus::class,
        'requested_permissions' => 'array',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'approver_id');
    }
}
