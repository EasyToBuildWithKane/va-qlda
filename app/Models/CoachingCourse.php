<?php

namespace App\Models;

use App\Support\Enums\CoachingCourseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property float|int|null $progress_percent_computed Set at runtime for Inertia show
 */
class CoachingCourse extends Model
{
    protected $table = 'coaching_courses';

    protected $fillable = [
        'code',
        'name',
        'description',
        'objectives',
        'student_id',
        'coach_id',
        'status',
        'start_date',
        'end_date',
        'total_fee',
        'hourly_rate',
        'total_hours',
        'created_by',
    ];

    protected $casts = [
        'status' => CoachingCourseStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'total_fee' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'total_hours' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (CoachingCourse $course) {
            if ($course->code === null) {
                $course->code = 'COACH-'.str_pad((string) $course->id, 3, '0', STR_PAD_LEFT);
                $course->saveQuietly();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'student_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coach_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CoachingSession::class, 'course_id')->orderBy('session_number');
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(CoachingProgress::class, 'course_id');
    }

    public function progressPercent(): int
    {
        $total = $this->sessions()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->sessions()
            ->where('status', \App\Support\Enums\CoachingSessionStatus::Completed->value)
            ->count();

        return (int) round(100 * $completed / $total);
    }
}
