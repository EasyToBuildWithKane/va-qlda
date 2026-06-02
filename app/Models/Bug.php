<?php

namespace App\Models;

use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $code
 * @property int $project_id
 * @property int|null $task_id
 * @property string $title
 * @property BugSeverity $severity
 * @property TaskPriority $priority
 * @property BugStatus $status
 * @property int|null $reporter_employee_id
 * @property string|null $reporter_name
 * @property string|null $reporter_email
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Bug extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'project_id',
        'task_id',
        'title',
        'description',
        'steps_to_reproduce',
        'expected',
        'actual',
        'environment',
        'severity',
        'priority',
        'status',
        'reporter_employee_id',
        'reporter_name',
        'reporter_email',
        'assignee_id',
        'resolved_at',
    ];

    protected $casts = [
        'severity' => BugSeverity::class,
        'priority' => TaskPriority::class,
        'status' => BugStatus::class,
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Human-friendly reference (BUG-0001) assigned once the id exists.
        static::created(function (Bug $bug) {
            if ($bug->code === null) {
                $bug->code = 'BUG-'.str_pad((string) $bug->id, 4, '0', STR_PAD_LEFT);
                $bug->saveQuietly();
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            BugStatus::Resolved->value,
            BugStatus::Closed->value,
            BugStatus::WontFix->value,
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    /** The person fixing the bug (người sửa). */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function reporterName(): string
    {
        return $this->reporter?->full_name ?? $this->reporter_name ?? 'Khách';
    }
}
