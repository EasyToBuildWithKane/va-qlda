<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property int $criterion_id
 * @property float|int $weight
 * @property string|null $required_score_label
 * @property bool $include_in_total
 * @property int $sort_order
 */
class EvaluationTemplateCriterion extends Model
{
    protected $table = 'evaluation_template_criteria';

    protected $fillable = [
        'template_id',
        'criterion_id',
        'weight',
        'required_score_label',
        'include_in_total',
        'sort_order',
    ];

    protected $casts = [
        'weight' => 'float',
        'include_in_total' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }
}
