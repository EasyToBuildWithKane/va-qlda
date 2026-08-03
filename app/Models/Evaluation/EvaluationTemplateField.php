<?php

namespace App\Models\Evaluation;

use App\Support\Enums\EvaluationTemplateFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property string $field_key
 * @property string $label
 * @property EvaluationTemplateFieldType|string $field_type
 * @property array|null $options
 * @property bool $is_required
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property int $sort_order
 */
class EvaluationTemplateField extends Model
{
    protected $table = 'evaluation_template_fields';

    protected $fillable = [
        'template_id',
        'field_key',
        'label',
        'field_type',
        'options',
        'is_required',
        'placeholder',
        'help_text',
        'sort_order',
    ];

    protected $casts = [
        'field_type' => EvaluationTemplateFieldType::class,
        'options' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }
}
