<?php

namespace App\Models;

use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $sprint_id
 * @property int|null $parent_id
 * @property int|null $epic_id
 * @property string|null $story_points
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property int|null $assignee_id
 * @property int|null $reporter_id
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $work_started_at
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $estimate_hours
 * @property string|null $actual_hours
 * @property string|null $completion_note
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $hours_timing
 * @property string|null $sla_result
 * @property int $progress
 * @property int $order_column
 */
class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'sprint_id',
        'parent_id',
        'epic_id',
        'title',
        'description',
        'status',
        'priority',
        'phase',
        'is_milestone',
        'assignee_id',
        'reporter_id',
        'reviewer_id',
        'start_date',
        'work_started_at',
        'due_date',
        'estimate_hours',
        'actual_hours',
        'completion_note',
        'completed_at',
        'hours_timing',
        'sla_result',
        'story_points',
        'progress',
        'order_column',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'phase' => TaskPhase::class,
        'is_milestone' => 'boolean',
        'start_date' => 'date',
        'work_started_at' => 'datetime',
        'due_date' => 'date',
        'estimate_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'completed_at' => 'datetime',
        'story_points' => 'decimal:2',
        'progress' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('order_column');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignees');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    /** Tasks this task depends on (predecessors). */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_id')
            ->withTimestamps();
    }

    /** Tasks that depend on this task (successors). */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_id', 'task_id')
            ->withTimestamps();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_watchers')->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    /** Total logged cost on this task. */
    public function laborCost(): float
    {
        $logs = $this->relationLoaded('worklogs') ? $this->worklogs : $this->worklogs();

        return (float) $logs->sum('cost');
    }
}
