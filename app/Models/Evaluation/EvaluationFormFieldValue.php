<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $form_field_id
 * @property string|null $value
 */
class EvaluationFormFieldValue extends Model
{
    protected $table = 'evaluation_form_field_values';

    protected $fillable = [
        'submission_id',
        'form_field_id',
        'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormSubmission::class, 'submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EvaluationFormField::class, 'form_field_id');
    }
}
