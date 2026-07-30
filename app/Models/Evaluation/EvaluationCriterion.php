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
 * @property list<array{code: string, label: string, description: string, weight: int|float}>|null $score_levels
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 */
class EvaluationCriterion extends Model
{
    use SoftDeletes;

    public const CODE_PREFIX = 'TCVA';

    public const MIN_SCORE_LEVELS = 2;

    public const MAX_SCORE_LEVELS = 10;

    /** @var list<array{code: string, label: string, description: string, weight: int}> */
    public const DEFAULT_SCORE_LEVELS = [
        ['code' => 'M1', 'label' => 'Không đáp ứng', 'description' => '', 'weight' => 1],
        ['code' => 'M2', 'label' => 'Cần cố gắng hơn', 'description' => '', 'weight' => 2],
        ['code' => 'M3', 'label' => 'Đạt yêu cầu', 'description' => '', 'weight' => 3],
        ['code' => 'M4', 'label' => 'Tốt', 'description' => '', 'weight' => 4],
        ['code' => 'M5', 'label' => 'Rất tốt', 'description' => '', 'weight' => 5],
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
        'score_levels',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'scope' => EvaluationCriterionScope::class,
        'allow_half_score' => 'boolean',
        'score_levels' => 'array',
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
     * Normalized score levels for UI / API (1-based index preserved by array order).
     *
     * @return list<array{code: string, label: string, description: string, weight: int|float}>
     */
    public function normalizedScoreLevels(): array
    {
        return static::normalizeScoreLevels($this->score_levels, (bool) $this->allow_half_score);
    }

    /**
     * @return list<array{code: string, label: string, description: string, weight: int|float}>
     */
    public static function normalizeScoreLevels(mixed $levels, bool $allowHalfScore = false): array
    {
        if (! is_array($levels) || $levels === []) {
            return self::DEFAULT_SCORE_LEVELS;
        }

        $out = [];
        foreach ($levels as $level) {
            if (! is_array($level)) {
                continue;
            }
            $label = trim((string) ($level['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $weight = round((float) ($level['weight'] ?? 0) * 2) / 2;
            if (! $allowHalfScore) {
                $weight = round($weight);
            }
            $code = trim((string) ($level['code'] ?? ''));
            $out[] = [
                'code' => $code !== '' ? $code : ('M'.(count($out) + 1)),
                'label' => $label,
                'description' => trim((string) ($level['description'] ?? '')),
                'weight' => fmod($weight, 1.0) === 0.0 ? (int) $weight : $weight,
            ];
        }

        return $out !== [] ? array_values($out) : self::DEFAULT_SCORE_LEVELS;
    }

    /**
     * Next criteria_code suggestion — TCVA001, TCVA002, …
     */
    public static function suggestNextCode(): string
    {
        $codes = static::query()
            ->withTrashed()
            ->pluck('criteria_code');

        $max = 0;
        $prefix = preg_quote(self::CODE_PREFIX, '/');

        foreach ($codes as $code) {
            $raw = (string) $code;
            if (preg_match('/^'.$prefix.'(\d+)$/i', $raw, $m)) {
                $max = max($max, (int) $m[1]);
            } elseif (preg_match('/^\d+$/', $raw)) {
                // Legacy numeric codes from earlier iterations
                $max = max($max, (int) $raw);
            }
        }

        return self::CODE_PREFIX.sprintf('%03d', $max + 1);
    }
}
