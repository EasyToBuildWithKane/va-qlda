<?php

namespace App\Models;

use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseRunResult;
use App\Support\Enums\TestCaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $suite_id
 * @property int|null $task_id
 * @property int|null $blocker_id
 * @property string|null $code
 * @property string $title
 * @property string|null $preconditions
 * @property array<int, array{step:string, expected?:string}>|null $steps
 * @property string|null $expected_result
 * @property TestCasePriority $priority
 * @property TestCaseStatus $status
 * @property int|null $owner_id
 * @property string|null $last_result
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property int|null $last_run_by_id
 * @property string|null $last_actual_result
 * @property string|null $last_run_note
 */
class TestCase extends Model
{
    protected $fillable = [
        'project_id',
        'suite_id',
        'task_id',
        'blocker_id',
        'code',
        'title',
        'preconditions',
        'steps',
        'expected_result',
        'priority',
        'status',
        'owner_id',
        'last_result',
        'last_run_at',
        'last_run_by_id',
        'last_actual_result',
        'last_run_note',
    ];

    protected $casts = [
        'priority' => TestCasePriority::class,
        'status' => TestCaseStatus::class,
        'steps' => 'array',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TestCase $testCase) {
            if (! $testCase->code) {
                $seq = static::query()
                    ->where('project_id', $testCase->project_id)
                    ->count() + 1;
                $testCase->code = 'TC-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Blocker::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function lastRunBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'last_run_by_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TestCaseRun::class)->latest('executed_at');
    }

    public function isNotRun(): bool
    {
        return $this->last_result === null;
    }

    public function lastRunResult(): ?TestCaseRunResult
    {
        return $this->last_result ? TestCaseRunResult::tryFrom($this->last_result) : null;
    }
}
