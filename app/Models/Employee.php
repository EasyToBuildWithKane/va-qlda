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
 * @property int|null $cms_user_id
 * @property string $code
 * @property string $full_name
 * @property string|null $email
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property-read string $name Alias of full_name for uniform UI/resource output
 */
class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'cms_user_id',
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

    /**
     * Org-team memberships (a person can sit in more than one team/section).
     */
    public function orgMemberships(): HasMany
    {
        return $this->hasMany(OrgTeamMember::class);
    }

    /**
     * Time logged by this person across all tasks.
     */
    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    // ── Talent module ────────────────────────────────────────────────────

    /** Leveled skill matrix (richer than the quick `skills` JSON list). */
    public function skillEntries(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class)->orderByDesc('level')->orderBy('name');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class)->orderByDesc('issued_at');
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(PerformanceKpi::class);
    }

    public function learningItems(): HasMany
    {
        return $this->hasMany(LearningItem::class)->latest('updated_at');
    }

    /** 360° reviews where this person is the subject. */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(FeedbackReview::class)->latest();
    }

    public function successionPlan(): HasOne
    {
        return $this->hasOne(SuccessionPlan::class);
    }

    /**
     * Uniform display name. The table stores `full_name`; expose it as `name`
     * so resources and the UI can rely on a single attribute everywhere.
     */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
}
