<?php

namespace App\Models;

use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $task_id
 * @property string $title
 * @property string|null $description
 * @property BlockerSeverity $severity
 * @property BlockerStatus $status
 * @property int|null $raised_by_id
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon|null $raised_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $resolution
 */
class Blocker extends Model
{
    protected $fillable = [
        'project_id',
        'task_id',
        'title',
        'description',
        'severity',
        'status',
        'raised_by_id',
        'owner_id',
        'raised_at',
        'resolved_at',
        'resolution',
    ];

    protected $casts = [
        'severity' => BlockerSeverity::class,
        'status' => BlockerStatus::class,
        'raised_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', BlockerStatus::Resolved->value);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'raised_by_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }
}
