<?php

namespace App\Support\Coaching;

use App\Models\CoachingCourse;
use Illuminate\Support\Collection;

class CoachingStudentMetrics
{
    /**
     * Đếm học viên phân biệt: ưu tiên employee (student_id), không thì tên tự do (student_name).
     *
     * @param  iterable<int, CoachingCourse>  $courses
     */
    public static function countDistinct(iterable $courses): int
    {
        return self::distinctKeys($courses)->count();
    }

    public static function countDistinctInDatabase(): int
    {
        return self::countDistinct(
            CoachingCourse::query()->get(['id', 'student_id', 'student_name'])
        );
    }

    /**
     * @param  iterable<int, CoachingCourse>  $courses
     * @return Collection<int, string>
     */
    public static function distinctKeys(iterable $courses): Collection
    {
        return collect($courses)->map(function (CoachingCourse $course) {
            if ($course->student_id) {
                return 'id:'.$course->student_id;
            }

            $name = trim((string) ($course->student_name ?? ''));
            if ($name !== '') {
                return 'name:'.mb_strtolower($name, 'UTF-8');
            }

            return null;
        })->filter()->unique()->values();
    }
}
