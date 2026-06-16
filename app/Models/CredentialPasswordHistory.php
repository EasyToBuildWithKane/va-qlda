<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialPasswordHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'credential_id',
        'encrypted_password',
        'changed_by',
        'changed_at',
        'notes',
    ];

    protected $casts = [
        'encrypted_password' => 'encrypted',
        'changed_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'changed_by');
    }
}
