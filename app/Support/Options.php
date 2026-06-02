<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
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

/**
 * Shared option payloads for the Inertia forms/pickers across the
 * project-management module, so each controller doesn't rebuild them.
 */
class Options
{
    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string, avatar_path:string|null}> */
    public static function employees()
    {
        return Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'avatar_path' => $e->avatar_path,
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string, code:string, color:string}> */
    public static function projects()
    {
        return Project::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'color' => $p->color,
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string, code:string, color:string}> */
    public static function departments()
    {
        return Department::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'color' => $d->color,
            ]);
    }

    /** Phòng ban phụ trách mặc định khi tạo dự án (Phòng Công nghệ). */
    public static function defaultOwnerDepartmentId(): ?int
    {
        $code = config('project.default_owner_department_code');

        if ($code) {
            $byCode = Department::query()->where('code', $code)->value('id');
            if ($byCode) {
                return $byCode;
            }
        }

        return Department::query()
            ->where('name', 'like', '%Công nghệ%')
            ->orderBy('sort_order')
            ->value('id');
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
            'bugSeverity' => BugSeverity::options(),
            'bugStatus' => BugStatus::options(),
            'feedbackCategory' => FeedbackCategory::options(),
            'feedbackStatus' => FeedbackStatus::options(),
            'projectAttachmentCategory' => ProjectAttachmentCategory::options(),
        ];
    }
}
