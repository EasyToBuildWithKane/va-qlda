<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $hours
 * @property string|null $note
 * @property string|null $rate_snapshot
 * @property string|null $cost
 */
class Worklog extends Model
{
    protected $fillable = [
        'task_id',
        'employee_id',
        'date',
        'hours',
        'note',
        'rate_snapshot',
        'cost',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'rate_snapshot' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
