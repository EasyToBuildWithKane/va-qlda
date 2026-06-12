<?php

namespace App\Domain\DailyReport\Models;

use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Models\Employee;
use App\Support\Concerns\HasUuid;
use App\Support\Enums\ReportStatus;
use Database\Factories\DailyReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $uuid
 * @property int $employee_id
 * @property int|null $project_id Legacy denormalized — see docs/DAILY_REPORT_PROJECTS.md
 * @property array<int, array{id: int, name?: string, code?: string}>|null $projects Source of truth for linked projects
 * @property \Illuminate\Support\Carbon $date
 * @property string $title
 * @property ReportStatus $status
 * @property bool $is_late
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class DailyReport extends Model
{
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'employee_id',
        'project_id',
        'projects',
        'date',
        'title',
        'goals_today',
        'progress_update',
        'blockers',
        'improvement_suggestions',
        'plan_tomorrow',
        'status',
        'is_late',
        'submitted_at',
        'reviewed_at',
        'review_notes',
        'task_status_snapshot',
    ];

    protected $casts = [
        'status' => ReportStatus::class,
        'is_late' => 'boolean',
        'projects' => 'array',
        'task_status_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return DailyReportFactory::new();
    }

    /**
     * Store the report date as date-only ('Y-m-d') so it is identical across
     * MySQL and SQLite and the unique (employee_id, date) rule matches. A
     * plain 'date' cast would persist 'Y-m-d H:i:s' and break that match.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => Carbon::parse($value),
            set: fn (mixed $value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'title',
                'is_late',
                'submitted_at',
                'reviewed_at',
                'review_notes',
                'projects',
                'goals_today',
                'progress_update',
                'blockers',
            ])
            ->useLogName('daily_report')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ---- Relations ------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(DailyReportScore::class, 'report_id');
    }

    // ---- Scopes ---------------------------------------------------------

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeOnDate(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Submitted);
    }

    // ---- State helpers --------------------------------------------------

    public function isEditable(): bool
    {
        return $this->status === ReportStatus::Draft;
    }

    public function isLocked(): bool
    {
        return $this->status === ReportStatus::Reviewed;
    }

    /** First project id (legacy column / list filter). */
    public function primaryProjectId(): ?int
    {
        return ReportProjectSync::legacyProjectId($this->projects);
    }

    /**
     * @return int[]
     */
    public function linkedProjectIds(): array
    {
        if (! is_array($this->projects)) {
            return [];
        }

        return array_values(array_map(
            fn ($p) => (int) $p['id'],
            $this->projects,
        ));
    }
}
