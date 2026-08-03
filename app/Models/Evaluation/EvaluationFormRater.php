<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property string $role_key
 * @property string $label
 * @property float $weight_percent
 * @property int $sort_order
 */
class EvaluationFormRater extends Model
{
    protected $table = 'evaluation_form_raters';

    protected $fillable = [
        'form_id',
        'role_key',
        'label',
        'weight_percent',
        'sort_order',
    ];

    protected $casts = [
        'weight_percent' => 'float',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }
}
