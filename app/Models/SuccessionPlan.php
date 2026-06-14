<?php

namespace App\Models;

use App\Support\Enums\SuccessionReadiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property SuccessionReadiness $readiness
 * @property int|null $risk_score
 * @property int|null $retention_score
 * @property int|null $promotion_score
 * @property string|null $target_role
 * @property string|null $note
 */
class SuccessionPlan extends Model
{
    protected $fillable = [
        'employee_id',
        'readiness',
        'risk_score',
        'retention_score',
        'promotion_score',
        'target_role',
        'note',
    ];

    protected $casts = [
        'readiness' => SuccessionReadiness::class,
        'risk_score' => 'integer',
        'retention_score' => 'integer',
        'promotion_score' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
