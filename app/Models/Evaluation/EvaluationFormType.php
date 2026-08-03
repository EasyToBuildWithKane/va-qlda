<?php

namespace App\Models\Evaluation;

use App\Models\SystemAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $created_by
 */
class EvaluationFormType extends Model
{
    protected $table = 'evaluation_form_types';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(EvaluationForm::class, 'type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
