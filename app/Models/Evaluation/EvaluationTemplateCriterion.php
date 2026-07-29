<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property string $criteria_code
 * @property string $criteria_name
 * @property string $category
 */
class EvaluationTemplateCriterion extends Model
{
    protected $table = 'evaluation_template_criteria';

    protected $fillable = [
        'template_id',
        'criteria_code',
        'criteria_name',
        'category',
        'description',
        'point_value',
        'max_points',
        'max_frequency',
        'weight',
        'required_score',
        'importance',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'point_value' => 'integer',
        'max_points' => 'integer',
        'max_frequency' => 'integer',
        'weight' => 'decimal:2',
        'required_score' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }
}
