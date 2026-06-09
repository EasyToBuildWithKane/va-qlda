<?php

namespace App\Models;

use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string|null $code
 * @property int|null $project_id
 * @property FeedbackCategory $category
 * @property string $title
 * @property string $description
 * @property int|null $rating
 * @property TaskPriority $priority
 * @property FeedbackStatus $status
 * @property int|null $reporter_employee_id
 * @property string|null $reporter_name
 * @property string|null $reporter_email
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'code',
        'project_id',
        'category',
        'title',
        'description',
        'rating',
        'priority',
        'status',
        'reporter_employee_id',
        'reporter_name',
        'reporter_email',
        'assignee_id',
        'resolved_at',
    ];

    protected $casts = [
        'category' => FeedbackCategory::class,
        'priority' => TaskPriority::class,
        'status' => FeedbackStatus::class,
        'rating' => 'integer',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Feedback $feedback) {
            if ($feedback->code === null) {
                $feedback->code = 'FB-'.str_pad((string) $feedback->id, 4, '0', STR_PAD_LEFT);
                $feedback->saveQuietly();
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            FeedbackStatus::Resolved->value,
            FeedbackStatus::Declined->value,
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    /** The person handling the feedback (người sửa feedback). */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FeedbackActivity::class)->latest();
    }

    public function reporterName(): string
    {
        return $this->reporter?->full_name ?? $this->reporter_name ?? 'Khách';
    }
}
