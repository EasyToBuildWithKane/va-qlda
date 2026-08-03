<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property string $field_key
 * @property string $label
 * @property string $field_type
 * @property bool $is_enabled
 * @property int $sort_order
 */
class EvaluationFormField extends Model
{
    protected $table = 'evaluation_form_fields';

    public const DEFAULT_FIELDS = [
        [
            'field_key' => 'evaluator_comment',
            'label' => 'Ý kiến người đánh giá',
            'field_type' => 'textarea',
            'is_enabled' => true,
            'sort_order' => 0,
        ],
        [
            'field_key' => 'self_next_plan',
            'label' => 'Kế hoạch bản thân trong lần tới',
            'field_type' => 'textarea',
            'is_enabled' => true,
            'sort_order' => 1,
        ],
    ];

    protected $fillable = [
        'form_id',
        'field_key',
        'label',
        'field_type',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }
}
