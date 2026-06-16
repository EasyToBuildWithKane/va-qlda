<?php

namespace App\Models;

use App\Support\Enums\CredentialRelationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialRelation extends Model
{
    protected $fillable = [
        'source_id',
        'target_id',
        'relation_type',
        'label',
        'created_by',
    ];

    protected $casts = [
        'relation_type' => CredentialRelationType::class,
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Credential::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Credential::class, 'target_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }
}
