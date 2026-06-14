<?php

namespace App\Models;

use App\Support\Enums\CoachingSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachingSession extends Model
{
    protected $table = 'coaching_sessions';

    protected $fillable = [
        'course_id',
        'title',
        'session_number',
        'date',
        'start_time',
        'end_time',
        'total_hours',
        'topic',
        'content',
        'notes',
        'status',
    ];

    protected $casts = [
        'status' => CoachingSessionStatus::class,
        'date' => 'date',
        'total_hours' => 'decimal:2',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(CoachingCourse::class, 'course_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CoachingSessionMaterial::class, 'session_id')->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CoachingAssignment::class, 'session_id');
    }
}
