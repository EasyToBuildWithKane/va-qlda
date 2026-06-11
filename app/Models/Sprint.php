<?php

namespace App\Models;

use App\Support\Enums\SprintStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $goal
 * @property SprintStatus $status
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property int $sort_order
 */
class Sprint extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'status',
        'start_date',
        'end_date',
        'sort_order',
    ];

    protected $casts = [
        'status' => SprintStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Progress = % root tasks đã hoàn thành trong sprint. */
    public function progress(): int
    {
        $query = $this->tasks()->whereNull('parent_id');
        $tasks = $this->relationLoaded('tasks')
            ? $this->tasks->whereNull('parent_id')
            : $query->get(['status']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $done = $tasks->filter(fn ($t) => $t->status === TaskStatus::Done)->count();

        return (int) round($done / $tasks->count() * 100);
    }
}
