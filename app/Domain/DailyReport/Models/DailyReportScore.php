<?php

namespace App\Domain\DailyReport\Models;

use App\Models\Employee;
use App\Support\Enums\Grade;
use Database\Factories\DailyReportScoreFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property float $total_score
 * @property Grade $grade
 * @property int $reviewer_id
 */
class DailyReportScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'task_completion',
        'skill_score',
        'attitude_score',
        'kaizen_score',
        'expertise_score',
        'total_score',
        'grade',
        'reviewer_id',
        'notes',
    ];

    protected $casts = [
        'task_completion' => 'decimal:2',
        'skill_score' => 'decimal:2',
        'attitude_score' => 'decimal:2',
        'kaizen_score' => 'decimal:2',
        'expertise_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'grade' => Grade::class,
    ];

    protected static function newFactory(): Factory
    {
        return DailyReportScoreFactory::new();
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'report_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
