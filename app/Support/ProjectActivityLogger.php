<?php

namespace App\Support;

use App\Models\Blocker;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\Task;

class ProjectActivityLogger
{
    public static function log(
        Project $project,
        string $event,
        string $description,
        ?array $meta = null,
        ?int $employeeId = null,
    ): void {
        ProjectActivity::create([
            'project_id' => $project->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Project $project, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'created',
            'Tạo dự án mới',
            ['code' => $project->code, 'name' => $project->name],
            $account?->employee_id,
        );
    }

    public static function duplicated(Project $project, Project $source, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'duplicated',
            "Nhân bản từ dự án {$source->code}",
            ['source_id' => $source->id, 'source_code' => $source->code],
            $account?->employee_id,
        );
    }

    public static function archived(Project $project, ?SystemAccount $account): void
    {
        self::log($project, 'archived', 'Lưu trữ / kết thúc dự án', null, $account?->employee_id);
    }

    public static function deleted(Project $project, ?SystemAccount $account): void
    {
        self::log($project, 'deleted', 'Xoá dự án', ['code' => $project->code], $account?->employee_id);
    }

    /** @param  array<string, mixed>  $changes */
    public static function updated(Project $project, ?SystemAccount $account, array $changes): void
    {
        $summary = NotificationChangeSummary::project($changes);
        if ($summary === null) {
            return;
        }

        self::log(
            $project,
            'updated',
            $summary,
            ['fields' => array_keys($changes)],
            $account?->employee_id,
        );
    }

    public static function memberAdded(Project $project, Employee $member, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'member_added',
            'Thêm thành viên: '.$member->full_name,
            ['employee_id' => $member->id],
            $account?->employee_id,
        );
    }

    public static function memberUpdated(Project $project, Employee $member, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'member_updated',
            'Cập nhật thành viên: '.$member->full_name,
            ['employee_id' => $member->id],
            $account?->employee_id,
        );
    }

    public static function memberRemoved(Project $project, Employee $member, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'member_removed',
            'Xoá thành viên: '.$member->full_name,
            ['employee_id' => $member->id],
            $account?->employee_id,
        );
    }

    public static function sprintCreated(Project $project, Sprint $sprint, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'sprint_created',
            'Tạo sprint: '.$sprint->name,
            ['sprint_id' => $sprint->id],
            $account?->employee_id,
        );
    }

    public static function sprintUpdated(Project $project, Sprint $sprint, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'sprint_updated',
            'Cập nhật sprint: '.$sprint->name,
            ['sprint_id' => $sprint->id],
            $account?->employee_id,
        );
    }

    public static function sprintDeleted(Project $project, string $sprintName, ?int $sprintId, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'sprint_deleted',
            'Xoá sprint: '.$sprintName,
            ['sprint_id' => $sprintId],
            $account?->employee_id,
        );
    }

    public static function taskRemoved(Project $project, Task $task, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'task_deleted',
            'Xoá công việc: '.$task->title,
            ['task_id' => $task->id],
            $account?->employee_id,
        );
    }

    public static function blockerRemoved(Project $project, Blocker $blocker, ?SystemAccount $account): void
    {
        self::log(
            $project,
            'blocker_deleted',
            'Xoá test case: '.$blocker->title,
            ['blocker_id' => $blocker->id],
            $account?->employee_id,
        );
    }
}
