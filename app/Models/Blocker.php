<?php

namespace App\Models;

use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property string $title
 * @property string|null $description
 * @property BlockerSeverity $severity
 * @property BlockerStatus $status
 * @property int|null $raised_by_id
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon|null $raised_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $code
 * @property string|null $root_cause
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $resolution
 * @property array<int, array{label?: string, url: string}>|null $evidence_links
 */
class Blocker extends Model
{
    protected $fillable = [
        'project_id',
        'task_id',
        'code',
        'title',
        'description',
        'root_cause',
        'severity',
        'status',
        'raised_by_id',
        'owner_id',
        'raised_at',
        'due_date',
        'resolved_at',
        'resolution',
        'evidence_links',
    ];

    protected $casts = [
        'severity' => BlockerSeverity::class,
        'status' => BlockerStatus::class,
        'raised_at' => 'datetime',
        'due_date' => 'date',
        'resolved_at' => 'datetime',
        'evidence_links' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Blocker $blocker) {
            if (! $blocker->code) {
                if ($blocker->project_id) {
                    $seq = static::query()->where('project_id', $blocker->project_id)->count() + 1;
                    $blocker->code = 'RSK-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
                } else {
                    $seq = static::query()->whereNull('project_id')->count() + 1;
                    $blocker->code = 'GEN-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
                }
            }
            $blocker->raised_at ??= now();
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            BlockerStatus::Resolved->value,
            BlockerStatus::Closed->value,
        ]);
    }

    /**
     * Danh sách mặc định trên /blockers: gồm đang xử lý và đã giải quyết, ẩn đã đóng.
     *
     * @param  Builder<Blocker>  $query
     * @return Builder<Blocker>
     */
    public function scopeListDefault(Builder $query): Builder
    {
        return $query->where('status', '!=', BlockerStatus::Closed->value);
    }

    /**
     * @param  Builder<Blocker>  $query
     * @return Builder<Blocker>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * Ưu tiên: nghiêm trọng → cao → trung bình → thấp, sau đó mới nhất.
     *
     * @param  Builder<Blocker>  $query
     * @return Builder<Blocker>
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        $critical = BlockerSeverity::Critical->value;
        $high = BlockerSeverity::High->value;
        $medium = BlockerSeverity::Medium->value;
        $low = BlockerSeverity::Low->value;

        return $query
            ->orderByRaw(
                'CASE severity
                    WHEN ? THEN 1
                    WHEN ? THEN 2
                    WHEN ? THEN 3
                    WHEN ? THEN 4
                    ELSE 5
                END',
                [$critical, $high, $medium, $low],
            )
            ->orderByDesc('raised_at');
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

    public function attachments(): HasMany
    {
        return $this->hasMany(BlockerAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BlockerActivity::class)->latest();
    }
}
