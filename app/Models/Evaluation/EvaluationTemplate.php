<?php

namespace App\Models\Evaluation;

use App\Support\Enums\EvaluationTemplateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property EvaluationTemplateType $template_type
 * @property string|null $description
 * @property bool $is_system
 */
class EvaluationTemplate extends Model
{
    protected $fillable = [
        'name',
        'template_type',
        'description',
        'is_system',
    ];

    protected $casts = [
        'template_type' => EvaluationTemplateType::class,
        'is_system' => 'boolean',
    ];

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationTemplateCriterion::class, 'template_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
