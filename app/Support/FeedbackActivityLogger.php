<?php

namespace App\Support;

use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\SystemAccount;

class FeedbackActivityLogger
{
    public static function log(Feedback $feedback, string $event, string $description, ?array $meta = null, ?int $employeeId = null): void
    {
        FeedbackActivity::create([
            'feedback_id' => $feedback->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Feedback $feedback, ?SystemAccount $account): void
    {
        self::log($feedback, 'created', 'Ghi nhận phản hồi mới', ['title' => $feedback->title], $account?->employee_id);
    }

    /** @param  array<string, mixed>  $changes */
    public static function updated(Feedback $feedback, ?SystemAccount $account, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $labels = [
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'category' => 'loại',
            'rating' => 'đánh giá',
            'priority' => 'ưu tiên',
            'status' => 'trạng thái',
            'assignee_id' => 'người xử lý',
        ];

        foreach ($changes as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            self::log(
                $feedback,
                'updated',
                'Cập nhật '.$labels[$field],
                ['field' => $field, 'value' => $value],
                $account?->employee_id,
            );
        }
    }

    public static function statusChanged(Feedback $feedback, string $from, string $to, ?SystemAccount $account): void
    {
        self::log(
            $feedback,
            'status_changed',
            "Đổi trạng thái: {$from} → {$to}",
            ['from' => $from, 'to' => $to],
            $account?->employee_id,
        );
    }

    public static function deleted(Feedback $feedback, ?SystemAccount $account): void
    {
        self::log($feedback, 'deleted', 'Xoá phản hồi', ['title' => $feedback->title], $account?->employee_id);
    }

    public static function commentAdded(Feedback $feedback, ?SystemAccount $account): void
    {
        self::log($feedback, 'comment', 'Thêm bình luận', null, $account?->employee_id);
    }

    public static function commentUpdated(Feedback $feedback, ?SystemAccount $account): void
    {
        self::log($feedback, 'comment_updated', 'Sửa bình luận', null, $account?->employee_id);
    }

    public static function commentDeleted(Feedback $feedback, ?SystemAccount $account): void
    {
        self::log($feedback, 'comment_deleted', 'Xoá bình luận', null, $account?->employee_id);
    }
}
