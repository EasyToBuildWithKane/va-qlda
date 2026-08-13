<?php

namespace App\Domain\RoutineTask\Models;

use App\Models\Employee;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Personal daily work log / routine checklist — not tied to Project/Sprint/cost.
 *
 * @property string $id
 * @property int $employee_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $work_date
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string|null $estimate_hours
 * @property string|null $actual_hours
 * @property int $progress_percent
 * @property string|null $blockers
 * @property string|null $risks
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class RoutineTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'status',
        'position',
        'work_date',
        'started_at',
        'ended_at',
        'estimate_hours',
        'actual_hours',
        'progress_percent',
        'blockers',
        'risks',
        'completed_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'position' => 'integer',
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'estimate_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'progress_percent' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * Statuses allowed on the personal routine board (subset of TaskStatus).
     *
     * @return array<int, string>
     */
    public static function allowedStatusValues(): array
    {
        return [
            TaskStatus::Todo->value,
            TaskStatus::InProgress->value,
            TaskStatus::Done->value,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RoutineTaskAttachment::class)->orderBy('id');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Done->value);
    }

    public function scopeByPosition(Builder $query): Builder
    {
        return $query
            ->orderByRaw('work_date is null')
            ->orderByDesc('work_date')
            ->orderBy('started_at')
            ->orderBy('position')
            ->orderBy('created_at');
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;
    }
}
