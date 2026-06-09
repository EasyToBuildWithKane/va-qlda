<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $employee_id
 * @property string $event
 * @property string $description
 * @property array|null $meta
 */
class ProjectActivity extends Model
{
    protected $fillable = [
        'project_id',
        'employee_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
