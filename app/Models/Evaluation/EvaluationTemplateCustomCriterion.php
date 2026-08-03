<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property string|null $custom_code
 * @property string $custom_name
 * @property string|null $custom_category
 * @property string|null $custom_description
 * @property float|int $weight
 * @property string|null $required_score_label
 * @property bool $include_in_total
 * @property int $sort_order
 */
class EvaluationTemplateCustomCriterion extends Model
{
    protected $table = 'evaluation_template_custom_criteria';

    protected $fillable = [
        'template_id',
        'custom_code',
        'custom_name',
        'custom_category',
        'custom_description',
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
}
