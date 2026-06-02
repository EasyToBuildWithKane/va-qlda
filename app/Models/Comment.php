<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int|null $employee_id
 * @property string|null $author_name
 * @property int|null $parent_id
 * @property string $body
 * @property array<string, array<int>>|null $reactions
 */
class Comment extends Model
{
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'parent_id',
        'employee_id',
        'author_name',
        'body',
        'reactions',
    ];

    protected $casts = [
        'reactions' => 'array',
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    public function authorName(): string
    {
        return $this->author?->full_name ?? $this->author_name ?? 'Khách';
    }
}
