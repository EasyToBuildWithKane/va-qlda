<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $form_criterion_id
 * @property string|null $score_level_code
 * @property string|null $score_level_label
 * @property float $score_weight
 */
class EvaluationFormScoreLine extends Model
{
    protected $table = 'evaluation_form_score_lines';

    protected $fillable = [
        'submission_id',
        'form_criterion_id',
        'score_level_code',
        'score_level_label',
        'score_weight',
    ];

    protected $casts = [
        'score_weight' => 'float',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormSubmission::class, 'submission_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormCriterion::class, 'form_criterion_id');
    }
}
