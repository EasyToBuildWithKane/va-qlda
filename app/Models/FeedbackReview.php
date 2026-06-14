<?php

namespace App\Models;

use App\Support\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One 360° review of an employee (self / manager / peer).
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $reviewer_id
 * @property ReviewType $review_type
 * @property string|null $period
 * @property int|null $rating_technical
 * @property int|null $rating_communication
 * @property int|null $rating_ownership
 * @property int|null $rating_leadership
 * @property int|null $rating_teamwork
 * @property string|null $comment
 */
class FeedbackReview extends Model
{
    /** Rating dimensions, keyed by column → label. */
    public const DIMENSIONS = [
        'rating_technical' => 'Chuyên môn',
        'rating_communication' => 'Giao tiếp',
        'rating_ownership' => 'Trách nhiệm',
        'rating_leadership' => 'Lãnh đạo',
        'rating_teamwork' => 'Làm việc nhóm',
    ];

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'review_type',
        'period',
        'rating_technical',
        'rating_communication',
        'rating_ownership',
        'rating_leadership',
        'rating_teamwork',
        'comment',
    ];

    protected $casts = [
        'review_type' => ReviewType::class,
        'rating_technical' => 'integer',
        'rating_communication' => 'integer',
        'rating_ownership' => 'integer',
        'rating_leadership' => 'integer',
        'rating_teamwork' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
