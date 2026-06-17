<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialImportLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'imported_count',
        'overwritten_count',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'imported_count' => 'integer',
        'overwritten_count' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'account_id');
    }
}
