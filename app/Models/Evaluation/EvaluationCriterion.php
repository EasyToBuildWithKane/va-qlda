<?php

namespace App\Models\Evaluation;

use App\Models\Department;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationCriterionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property EvaluationCriterionScope|string $scope
 * @property string|null $department_code
 * @property string|null $department_name
 * @property int|null $local_department_id
 * @property string $criteria_code
 * @property string $criteria_name
 * @property string $category
 * @property string|null $description
 * @property bool $allow_half_score
 * @property string $score_1
 * @property string $score_2
 * @property string $score_3
 * @property string $score_4
 * @property string $score_5
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 */
class EvaluationCriterion extends Model
{
    use SoftDeletes;

    public const DEFAULT_SCORE_LABELS = [
        1 => 'Không đáp ứng',
        2 => 'Cần cố gắng hơn',
        3 => 'Đạt yêu cầu',
        4 => 'Tốt',
        5 => 'Rất tốt',
    ];

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'scope',
        'department_code',
        'department_name',
        'local_department_id',
        'criteria_code',
        'criteria_name',
        'category',
        'description',
        'allow_half_score',
        'score_1',
        'score_2',
        'score_3',
        'score_4',
        'score_5',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'scope' => EvaluationCriterionScope::class,
        'allow_half_score' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function localDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'local_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('scope', EvaluationCriterionScope::General);
    }

    public function scopeForDepartment(Builder $query): Builder
    {
        return $query->where('scope', EvaluationCriterionScope::Department);
    }

    public function displayName(): string
    {
        if ($this->scope === EvaluationCriterionScope::Department
            && filled($this->department_name)) {
            return "[{$this->department_name}] {$this->criteria_name}";
        }

        return $this->criteria_name;
    }

    /**
     * Next numeric criteria_code suggestion (global).
     */
    public static function suggestNextCode(): string
    {
        $codes = static::query()
            ->withTrashed()
            ->pluck('criteria_code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^\d+$/', (string) $code)) {
                $max = max($max, (int) $code);
            }
        }

        return (string) ($max + 1);
    }
}
