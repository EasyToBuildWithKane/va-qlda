<?php

namespace App\Support;

use App\Models\Bug;
use App\Models\BugActivity;
use App\Models\SystemAccount;

class BugActivityLogger
{
    public static function log(Bug $bug, string $event, string $description, ?array $meta = null, ?int $employeeId = null): void
    {
        BugActivity::create([
            'bug_id' => $bug->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Bug $bug, ?SystemAccount $account): void
    {
        self::log($bug, 'created', 'Tạo bug mới', ['title' => $bug->title], $account?->employee_id);
    }

    /** @param  array<string, mixed>  $changes */
    public static function updated(Bug $bug, ?SystemAccount $account, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $labels = [
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'severity' => 'mức độ',
            'priority' => 'ưu tiên',
            'status' => 'trạng thái',
            'assignee_id' => 'người xử lý',
            'task_id' => 'công việc liên quan',
        ];

        foreach ($changes as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            self::log(
                $bug,
                'updated',
                'Cập nhật '.$labels[$field],
                ['field' => $field, 'value' => $value],
                $account?->employee_id,
            );
        }
    }

    public static function statusChanged(Bug $bug, string $from, string $to, ?SystemAccount $account): void
    {
        self::log(
            $bug,
            'status_changed',
            "Đổi trạng thái: {$from} → {$to}",
            ['from' => $from, 'to' => $to],
            $account?->employee_id,
        );
    }

    public static function deleted(Bug $bug, ?SystemAccount $account): void
    {
        self::log($bug, 'deleted', 'Xoá bug', ['title' => $bug->title], $account?->employee_id);
    }

    public static function commentAdded(Bug $bug, ?SystemAccount $account): void
    {
        self::log($bug, 'comment', 'Thêm bình luận', null, $account?->employee_id);
    }

    public static function commentUpdated(Bug $bug, ?SystemAccount $account): void
    {
        self::log($bug, 'comment_updated', 'Sửa bình luận', null, $account?->employee_id);
    }

    public static function commentDeleted(Bug $bug, ?SystemAccount $account): void
    {
        self::log($bug, 'comment_deleted', 'Xoá bình luận', null, $account?->employee_id);
    }
}
