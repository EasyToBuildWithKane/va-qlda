<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (account, tour) — tracks how far a user got through a guided tour.
 *
 * @property int $id
 * @property int $system_account_id
 * @property string $tour_key
 * @property int $current_step
 * @property int $total_steps
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class OnboardingProgress extends Model
{
    protected $table = 'onboarding_progress';

    protected $fillable = [
        'system_account_id',
        'tour_key',
        'current_step',
        'total_steps',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'system_account_id');
    }
}
