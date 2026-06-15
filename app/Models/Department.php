<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $color
 * @property int|null $manager_id
 * @property int $sort_order
 * @property bool $is_active
 */
class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'color',
        'manager_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'department_member')
            ->withPivot(['joined_at', 'is_active'])
            ->withTimestamps()
            ->orderBy('department_member.id');
    }
}
