<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $full_name
 * @property string|null $email
 * @property string|null $avatar_path
 * @property bool $is_active
 */
class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'full_name',
        'email',
        'phone',
        'avatar_path',
        'role_title',
        'join_date',
        'skills',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'join_date' => 'date',
        'skills' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The login account linked to this employee, if any.
     */
    public function account(): HasOne
    {
        return $this->hasOne(SystemAccount::class);
    }

    /**
     * Projects this person is a member of (with their per-project rate on the pivot).
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_member')
            ->withPivot(['role', 'rate_type', 'rate', 'allocation', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
}
