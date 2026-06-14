<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachingProgress extends Model
{
    public $timestamps = false;

    protected $table = 'coaching_progress';

    protected $fillable = [
        'course_id',
        'session_id',
        'system_account_id',
        'is_viewed',
        'is_in_progress',
        'is_completed',
        'updated_at',
    ];

    protected $casts = [
        'is_viewed' => 'boolean',
        'is_in_progress' => 'boolean',
        'is_completed' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(CoachingCourse::class, 'course_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'system_account_id');
    }
}
