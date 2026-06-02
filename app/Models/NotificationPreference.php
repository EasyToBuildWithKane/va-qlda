<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'system_account_id',
        'disabled_types',
        'channel_in_app',
        'channel_email',
        'channel_push',
    ];

    protected $casts = [
        'disabled_types' => 'array',
        'channel_in_app' => 'boolean',
        'channel_email' => 'boolean',
        'channel_push' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'system_account_id');
    }

    public function isTypeEnabled(string $type): bool
    {
        if (! $this->channel_in_app) {
            return false;
        }

        $disabled = $this->disabled_types ?? [];

        return ! in_array($type, $disabled, true);
    }
}
