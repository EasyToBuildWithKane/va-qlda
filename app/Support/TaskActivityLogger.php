<?php

namespace App\Support;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Models\TaskActivity;

class TaskActivityLogger
{
    public static function log(Task $task, string $event, string $description, ?array $meta = null, ?int $employeeId = null): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Task $task, ?SystemAccount $account): void
    {
        self::log(
            $task,
            'created',
            'Tạo công việc mới',
            ['title' => $task->title, 'parent_id' => $task->parent_id],
            $account?->employee_id,
        );
    }

    /** @param  array<string, mixed>  $changes */
    public static function updated(Task $task, ?SystemAccount $account, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $labels = [
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'status' => 'trạng thái',
            'priority' => 'ưu tiên',
            'phase' => 'giai đoạn',
            'sprint_id' => 'sprint',
            'assignee_id' => 'người thực hiện',
            'reviewer_id' => 'người duyệt',
            'start_date' => 'ngày bắt đầu',
            'due_date' => 'hạn',
            'estimate_hours' => 'giờ ước tính',
            'story_points' => 'story point',
            'progress' => 'tiến độ',
            'epic_id' => 'epic',
            'parent_id' => 'task cha',
        ];

        foreach ($changes as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            self::log(
                $task,
                'updated',
                'Cập nhật '.$labels[$field],
                ['field' => $field, 'value' => $value],
                $account?->employee_id,
            );
        }
    }

    public static function statusChanged(Task $task, string $from, string $to, ?SystemAccount $account): void
    {
        self::log(
            $task,
            'status_changed',
            "Đổi trạng thái: {$from} → {$to}",
            ['from' => $from, 'to' => $to],
            $account?->employee_id,
        );
    }

    public static function completed(Task $task, ?SystemAccount $account): void
    {
        $estimate = $task->estimate_hours !== null ? (float) $task->estimate_hours : null;
        $actual = $task->actual_hours !== null ? (float) $task->actual_hours : null;

        self::log(
            $task,
            'completed',
            'Hoàn thành công việc',
            [
                'completed_at' => $task->completed_at?->toIso8601String(),
                'estimate_hours' => $estimate,
                'actual_hours' => $actual,
                'hours_timing' => $task->hours_timing,
                'sla_result' => $task->sla_result,
                'completion_note' => $task->completion_note,
            ],
            $account?->employee_id,
        );
    }

    public static function commentAdded(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'comment', 'Thêm bình luận', null, $account?->employee_id);
    }

    public static function attachmentAdded(Task $task, string $fileName, ?SystemAccount $account): void
    {
        self::log(
            $task,
            'attachment',
            "Đính kèm: {$fileName}",
            ['file' => $fileName],
            $account?->employee_id,
        );
    }

    public static function watcherChanged(Task $task, bool $watching, ?SystemAccount $account): void
    {
        self::log(
            $task,
            'watcher',
            $watching ? 'Bắt đầu theo dõi' : 'Ngừng theo dõi',
            null,
            $account?->employee_id,
        );
    }

    public static function subtaskAdded(Task $parent, Task $subtask, ?SystemAccount $account): void
    {
        self::log(
            $parent,
            'subtask',
            "Thêm công việc con: {$subtask->title}",
            ['subtask_id' => $subtask->id],
            $account?->employee_id,
        );
    }

    public static function deleted(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'deleted', 'Xoá công việc', ['title' => $task->title], $account?->employee_id);
    }

    public static function attachmentRemoved(Task $task, string $fileName, ?SystemAccount $account): void
    {
        self::log($task, 'attachment_removed', "Xoá đính kèm: {$fileName}", ['file' => $fileName], $account?->employee_id);
    }

    public static function worklogRemoved(Task $task, float $hours, ?SystemAccount $account): void
    {
        self::log($task, 'worklog_removed', "Xoá bản ghi {$hours}h", ['hours' => $hours], $account?->employee_id);
    }

    public static function assigneesSynced(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'assignees', 'Cập nhật danh sách người thực hiện', null, $account?->employee_id);
    }

    public static function dependenciesSynced(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'dependencies', 'Cập nhật phụ thuộc công việc', null, $account?->employee_id);
    }

    public static function commentUpdated(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'comment_updated', 'Sửa bình luận', null, $account?->employee_id);
    }

    public static function commentDeleted(Task $task, ?SystemAccount $account): void
    {
        self::log($task, 'comment_deleted', 'Xoá bình luận', null, $account?->employee_id);
    }
}
