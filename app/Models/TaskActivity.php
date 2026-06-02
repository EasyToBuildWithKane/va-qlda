<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $employee_id
 * @property string $event
 * @property string $description
 * @property array|null $meta
 */
class TaskActivity extends Model
{
    protected $fillable = [
        'task_id',
        'employee_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
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
