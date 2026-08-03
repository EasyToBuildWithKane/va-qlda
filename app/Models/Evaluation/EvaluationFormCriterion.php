<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property int|null $criterion_id
 * @property string $name
 * @property float $weight
 * @property string|null $required_score_label
 * @property string $evaluator_mode
 * @property array|null $evaluator_role_keys
 * @property int $sort_order
 */
class EvaluationFormCriterion extends Model
{
    protected $table = 'evaluation_form_criteria';

    protected $fillable = [
        'form_id',
        'criterion_id',
        'name',
        'weight',
        'required_score_label',
        'evaluator_mode',
        'evaluator_role_keys',
        'sort_order',
    ];

    protected $casts = [
        'weight' => 'float',
        'evaluator_role_keys' => 'array',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }
}
