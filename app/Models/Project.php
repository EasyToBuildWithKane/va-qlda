<?php

namespace App\Models;

use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $color
 * @property ProjectStatus $status
 * @property ProjectType $type
 * @property ProjectScope $scope
 * @property array<int, string>|null $scope_regions
 * @property array<int, int>|null $scope_departments
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $budget
 * @property string|null $actual_budget
 * @property int|null $manager_id
 * @property int|null $department_id
 * @property bool $is_active
 * @property int $sort_order
 */
class Project extends Model
{
    use HasFactory;

    /**
     * Assumed billable hours per month, used to convert monthly → hourly rate.
     * Reads from config/business.php; constant kept for backward-compat call sites.
     */
    public const MONTHLY_HOURS = 176;

    public static function monthlyHours(): int
    {
        return (int) config('business.project.monthly_hours', self::MONTHLY_HOURS);
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'status',
        'type',
        'scope',
        'scope_regions',
        'scope_departments',
        'start_date',
        'due_date',
        'budget',
        'actual_budget',
        'manager_id',
        'department_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status' => ProjectStatus::class,
        'type' => ProjectType::class,
        'scope' => ProjectScope::class,
        'scope_regions' => 'array',
        'scope_departments' => 'array',
        'start_date' => 'date',
        'due_date' => 'date',
        'budget' => 'decimal:2',
        'actual_budget' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ---- Relations ------------------------------------------------------

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_member')
            ->withPivot(['role', 'rate_type', 'rate', 'allocation', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderBy('start_date')->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class)->orderBy('name');
    }

    public function worklogs(): HasManyThrough
    {
        return $this->hasManyThrough(Worklog::class, Task::class);
    }

    public function blockers(): HasMany
    {
        return $this->hasMany(Blocker::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->latest();
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest();
    }

    // ---- Computed -------------------------------------------------------

    /**
     * Progress = % task gốc đã hoàn thành (theo trạng thái).
     * Uses the loaded `tasks` relation when present to avoid extra queries.
     */
    public function progress(): int
    {
        $tasks = $this->relationLoaded('tasks')
            ? $this->tasks->whereNull('parent_id')
            : $this->tasks()->whereNull('parent_id')->get(['status']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $done = $tasks->filter(fn ($t) => $t->status === TaskStatus::Done)->count();

        return (int) round($done / $tasks->count() * 100);
    }

    /** Total logged labour cost across all the project's tasks. */
    public function laborCost(): float
    {
        return (float) $this->worklogs()->sum('cost');
    }
}
