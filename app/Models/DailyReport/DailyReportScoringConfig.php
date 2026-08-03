<?php

namespace App\Models\DailyReport;

use App\Models\Department;
use App\Models\SystemAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-department daily-report scoring weights (Workspace).
 *
 * @property int $id
 * @property string $department_code
 * @property string|null $department_name
 * @property int|null $local_department_id
 * @property array<string, float|int> $weights
 * @property float $kaizen_bonus_max
 * @property string $status
 * @property int|null $updated_by
 */
class DailyReportScoringConfig extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DRAFT = 'draft';

    public const WEIGHT_KEYS = [
        'task_completion',
        'skill_score',
        'attitude_score',
        'expertise_score',
    ];

    protected $table = 'daily_report_scoring_configs';

    protected $fillable = [
        'department_code',
        'department_name',
        'local_department_id',
        'weights',
        'kaizen_bonus_max',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'weights' => 'array',
        'kaizen_bonus_max' => 'float',
        'local_department_id' => 'integer',
        'updated_by' => 'integer',
    ];

    public function localDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'local_department_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForDepartment(Builder $query, string $departmentCode): Builder
    {
        return $query->whereRaw('LOWER(department_code) = ?', [mb_strtolower(trim($departmentCode))]);
    }
}
