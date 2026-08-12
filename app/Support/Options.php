<?php

namespace App\Support;

use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\RateType;
use App\Support\Enums\Region;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseRunResult;
use App\Support\Enums\TestCaseStatus;

/**
 * Shared option payloads for Inertia forms/pickers.
 * Delegates to injectable Options services — use those directly in new code.
 *
 * @see \App\Support\Options\EmployeeOptions
 * @see \App\Support\Options\ProjectOptions
 * @see \App\Support\Options\DepartmentOptions
 */
class Options
{
    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string, avatar_path:string|null}> */
    public static function employees()
    {
        return app(\App\Support\Options\EmployeeOptions::class)->all();
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string, code:string, color:string}> */
    public static function projects()
    {
        return app(\App\Support\Options\ProjectOptions::class)->all();
    }

    /**
     * Danh sách phòng ban active (đã mirror từ HRM org-units khi local trống/thiếu).
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string, code:string, color:string}>
     */
    public static function departments()
    {
        return app(\App\Support\Options\DepartmentOptions::class)->all();
    }

    /** Phòng ban phụ trách mặc định khi tạo dự án (Phòng Công nghệ / PCN). */
    public static function defaultOwnerDepartmentId(): ?int
    {
        return app(\App\Support\Options\DepartmentOptions::class)->defaultOwnerId();
    }

    /** @return array<int, array{value:string, label:string}> */
    public static function regions(): array
    {
        return Region::options();
    }

    /**
     * Every enum option list the front-end forms/badges may need.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function enums(): array
    {
        return [
            'projectStatus' => ProjectStatus::options(),
            'sprintStatus' => SprintStatus::options(),
            'taskStatus' => TaskStatus::options(),
            'taskPriority' => TaskPriority::options(),
            'taskPhase' => TaskPhase::options(),
            'rateType' => RateType::options(),
            'blockerSeverity' => BlockerSeverity::options(),
            'blockerStatus' => BlockerStatus::options(),
            'feedbackCategory' => FeedbackCategory::options(),
            'feedbackStatus' => FeedbackStatus::options(),
            'projectAttachmentCategory' => ProjectAttachmentCategory::options(),
            'testCaseStatus' => TestCaseStatus::options(),
            'testCasePriority' => TestCasePriority::options(),
            'testCaseRunResult' => TestCaseRunResult::options(),
        ];
    }
}
