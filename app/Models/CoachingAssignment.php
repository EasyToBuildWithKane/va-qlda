<?php

namespace App\Models;

use App\Support\Enums\CoachingAssignmentStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachingAssignment extends Model
{
    protected $table = 'coaching_assignments';

    protected $fillable = [
        'session_id',
        'title',
        'description',
        'deadline',
        'priority',
        'status',
        'submission_path',
        'github_url',
        'notes',
    ];

    protected $casts = [
        'status' => CoachingAssignmentStatus::class,
        'priority' => TaskPriority::class,
        'deadline' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }
}
