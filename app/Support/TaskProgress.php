<?php

namespace App\Support;

use App\Support\Enums\TaskStatus;

/** Tiến độ task cố định theo trạng thái — không nhập tay. */
final class TaskProgress
{
    public static function fromStatus(TaskStatus|string $status): int
    {
        $value = $status instanceof TaskStatus ? $status->value : $status;

        return match ($value) {
            TaskStatus::Done->value => 100,
            TaskStatus::InReview->value => 66,
            TaskStatus::InProgress->value => 33,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function syncProgressFromStatus(array &$data): void
    {
        if (! array_key_exists('status', $data)) {
            unset($data['progress']);

            return;
        }

        $data['progress'] = self::fromStatus($data['status']);
    }
}
