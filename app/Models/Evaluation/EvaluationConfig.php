<?php

namespace App\Models\Evaluation;

use App\Models\Department;
use App\Models\SystemAccount;
use App\Support\Enums\EvaluationTemplateType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $department_code
 * @property string $department_name
 * @property int|null $local_department_id
 * @property EvaluationTemplateType $template_type
 * @property string $config_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property int|null $base_score
 * @property bool $is_active
 * @property int|null $created_by
 */
class EvaluationConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'department_code',
        'department_name',
        'local_department_id',
        'template_type',
        'config_name',
        'description',
        'effective_from',
        'effective_to',
        'base_score',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'template_type' => EvaluationTemplateType::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'base_score' => 'integer',
        'is_active' => 'boolean',
    ];

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class, 'config_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

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

    public function scopeCurrentlyEffective(Builder $query, string|\DateTimeInterface|null $on = null): Builder
    {
        $on = $on instanceof \DateTimeInterface
            ? $on->format('Y-m-d')
            : ($on ?? now()->toDateString());

        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $on)
            ->where(function (Builder $q) use ($on) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $on);
            });
    }
}
