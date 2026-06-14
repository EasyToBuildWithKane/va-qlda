<?php

namespace App\Models;

use App\Support\Enums\KpiPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property KpiPeriod $period_type
 * @property string $period
 * @property string $name
 * @property float|null $target
 * @property float|null $actual
 * @property string|null $unit
 * @property int $weight
 */
class PerformanceKpi extends Model
{
    protected $fillable = [
        'employee_id',
        'period_type',
        'period',
        'name',
        'target',
        'actual',
        'unit',
        'weight',
    ];

    protected $casts = [
        'period_type' => KpiPeriod::class,
        'target' => 'decimal:2',
        'actual' => 'decimal:2',
        'weight' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Achievement ratio (actual / target), capped at 1.2 (120%). Null if no target. */
    public function attainment(): ?float
    {
        if ($this->target === null || (float) $this->target == 0.0) {
            return null;
        }

        return min((float) $this->actual / (float) $this->target, 1.2);
    }
}
