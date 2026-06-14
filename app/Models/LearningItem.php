<?php

namespace App\Models;

use App\Support\Enums\LearningStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $title
 * @property string|null $provider
 * @property string|null $category
 * @property LearningStatus $status
 * @property int $progress
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class LearningItem extends Model
{
    protected $fillable = [
        'employee_id',
        'title',
        'provider',
        'category',
        'status',
        'progress',
        'url',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => LearningStatus::class,
        'progress' => 'integer',
        'started_at' => 'date',
        'completed_at' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
