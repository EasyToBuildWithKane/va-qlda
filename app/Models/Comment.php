<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int|null $employee_id
 * @property string|null $author_name
 * @property string $body
 */
class Comment extends Model
{
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'employee_id',
        'author_name',
        'body',
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function authorName(): string
    {
        return $this->author?->full_name ?? $this->author_name ?? 'Khách';
    }
}
