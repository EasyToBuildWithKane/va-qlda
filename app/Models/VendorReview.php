<?php

namespace App\Models;

use App\Support\Enums\ContractReviewRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_id
 * @property int|null $contract_id
 * @property int|null $reviewer_id
 * @property Carbon|null $reviewed_at
 * @property string|null $service_quality
 * @property string|null $sla
 * @property string|null $speed
 * @property string|null $price_satisfaction
 * @property string|null $stability
 * @property string|null $attitude
 * @property string|null $total_score
 * @property ContractReviewRecommendation|null $recommendation
 * @property string|null $note
 */
class VendorReview extends Model
{
    protected $fillable = [
        'vendor_id',
        'contract_id',
        'reviewer_id',
        'reviewed_at',
        'service_quality',
        'sla',
        'speed',
        'price_satisfaction',
        'stability',
        'attitude',
        'total_score',
        'recommendation',
        'note',
    ];

    protected $casts = [
        'reviewed_at' => 'date',
        'service_quality' => 'decimal:2',
        'sla' => 'decimal:2',
        'speed' => 'decimal:2',
        'price_satisfaction' => 'decimal:2',
        'stability' => 'decimal:2',
        'attitude' => 'decimal:2',
        'total_score' => 'decimal:2',
        'recommendation' => ContractReviewRecommendation::class,
    ];

    /** 6 tiêu chí chấm điểm (0–10). */
    public const CRITERIA = [
        'service_quality',
        'sla',
        'speed',
        'price_satisfaction',
        'stability',
        'attitude',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
