<?php

namespace App\Models\Evaluation;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $form_code
 * @property string $name
 * @property int|null $template_id
 * @property int $type_id
 * @property string $period_kind
 * @property int|null $period_month
 * @property int|null $period_year
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property bool $auto_create_next
 * @property int $manager_employee_id
 * @property \Illuminate\Support\Carbon $deadline
 * @property string $evaluation_order
 * @property bool $use_weight
 * @property string $status
 * @property int|null $created_by
 */
class EvaluationForm extends Model
{
    use SoftDeletes;

    public const CODE_PREFIX = 'PDG';

    protected $table = 'evaluation_forms';

    protected $fillable = [
        'form_code',
        'name',
        'template_id',
        'type_id',
        'period_kind',
        'period_month',
        'period_year',
        'period_start',
        'period_end',
        'auto_create_next',
        'manager_employee_id',
        'deadline',
        'evaluation_order',
        'use_weight',
        'status',
        'created_by',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'auto_create_next' => 'boolean',
        'deadline' => 'date',
        'use_weight' => 'boolean',
        'period_kind' => EvaluationFormPeriodKind::class,
        'evaluation_order' => EvaluationFormOrder::class,
        'status' => EvaluationFormStatus::class,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormType::class, 'type_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(EvaluationFormWatcher::class, 'form_id');
    }

    public function raters(): HasMany
    {
        return $this->hasMany(EvaluationFormRater::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(EvaluationFormField::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationFormCriterion::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(EvaluationFormAssignee::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActiveStatus(Builder $query): Builder
    {
        return $query->where('status', EvaluationFormStatus::Active);
    }

    /**
     * Next form_code suggestion — PDG001, PDG002, …
     */
    public static function suggestNextCode(): string
    {
        $codes = static::query()
            ->withTrashed()
            ->pluck('form_code');

        $max = 0;
        $prefix = preg_quote(self::CODE_PREFIX, '/');

        foreach ($codes as $code) {
            $raw = (string) $code;
            if (preg_match('/^'.$prefix.'(\d+)$/i', $raw, $m)) {
                $max = max($max, (int) $m[1]);
            } elseif (preg_match('/^\d+$/', $raw)) {
                $max = max($max, (int) $raw);
            }
        }

        return self::CODE_PREFIX.sprintf('%03d', $max + 1);
    }
}
