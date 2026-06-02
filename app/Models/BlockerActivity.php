<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $blocker_id
 * @property int|null $employee_id
 * @property string $event
 * @property string $description
 * @property array<string, mixed>|null $meta
 */
class BlockerActivity extends Model
{
    protected $fillable = [
        'blocker_id',
        'employee_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Blocker::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
