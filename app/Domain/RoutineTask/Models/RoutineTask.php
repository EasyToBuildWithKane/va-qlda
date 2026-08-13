<?php

namespace App\Domain\RoutineTask\Models;

use App\Models\Employee;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal routine checklist item — not tied to Project/Sprint/cost.
 *
 * @property string $id
 * @property int $employee_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property int $position
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
        'completed_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'position' => 'integer',
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
        return $query->orderBy('position')->orderBy('created_at');
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;
    }
}
