<?php

namespace App\Models\Evaluation;

use App\Support\Enums\EvaluationTemplateTargetKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $template_id
 * @property EvaluationTemplateTargetKind|string $kind
 * @property string $code
 * @property string $name
 * @property string|null $hrm_uuid
 * @property string $source
 * @property int $sort_order
 */
class EvaluationTemplateTarget extends Model
{
    protected $table = 'evaluation_template_targets';

    protected $fillable = [
        'template_id',
        'kind',
        'code',
        'name',
        'hrm_uuid',
        'source',
        'sort_order',
    ];

    protected $casts = [
        'kind' => EvaluationTemplateTargetKind::class,
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }
}
