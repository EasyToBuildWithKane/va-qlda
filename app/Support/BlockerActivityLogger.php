<?php

namespace App\Support;

use App\Models\Blocker;
use App\Models\BlockerActivity;
use App\Models\SystemAccount;

class BlockerActivityLogger
{
    public static function log(Blocker $blocker, string $event, string $description, ?array $meta = null, ?int $employeeId = null): void
    {
        BlockerActivity::create([
            'blocker_id' => $blocker->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function created(Blocker $blocker, ?SystemAccount $account): void
    {
        self::log(
            $blocker,
            'created',
            'Ghi nhận rủi ro / vướng mắc mới',
            ['title' => $blocker->title],
            $account?->employee_id,
        );
    }

    public static function updated(Blocker $blocker, ?SystemAccount $account, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $labels = [
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'root_cause' => 'nguyên nhân',
            'resolution' => 'hướng xử lý',
            'severity' => 'mức độ',
            'status' => 'trạng thái',
            'owner_id' => 'người phụ trách',
            'due_date' => 'hạn xử lý',
            'evidence_links' => 'link dẫn chứng',
        ];

        foreach ($changes as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            self::log(
                $blocker,
                'updated',
                'Cập nhật '.$labels[$field],
                ['field' => $field, 'value' => $value],
                $account?->employee_id,
            );
        }
    }

    public static function statusChanged(Blocker $blocker, string $from, string $to, ?SystemAccount $account): void
    {
        self::log(
            $blocker,
            'status_changed',
            "Chuyển trạng thái: {$from} → {$to}",
            ['from' => $from, 'to' => $to],
            $account?->employee_id,
        );
    }

    public static function commentAdded(Blocker $blocker, ?SystemAccount $account): void
    {
        self::log(
            $blocker,
            'comment',
            'Thêm bình luận trao đổi',
            null,
            $account?->employee_id,
        );
    }

    public static function attachmentAdded(Blocker $blocker, string $fileName, ?SystemAccount $account): void
    {
        self::log(
            $blocker,
            'attachment',
            "Đính kèm file: {$fileName}",
            ['file' => $fileName],
            $account?->employee_id,
        );
    }

    public static function attachmentRemoved(Blocker $blocker, string $fileName, ?SystemAccount $account): void
    {
        self::log(
            $blocker,
            'attachment_removed',
            "Xoá đính kèm: {$fileName}",
            ['file' => $fileName],
            $account?->employee_id,
        );
    }

    public static function deleted(Blocker $blocker, ?SystemAccount $account): void
    {
        self::log($blocker, 'deleted', 'Xoá vướng mắc', ['title' => $blocker->title], $account?->employee_id);
    }

    public static function bulkUpdated(Blocker $blocker, string $description, ?SystemAccount $account): void
    {
        self::log($blocker, 'bulk_updated', $description, null, $account?->employee_id);
    }

    public static function commentUpdated(Blocker $blocker, ?SystemAccount $account): void
    {
        self::log($blocker, 'comment_updated', 'Sửa bình luận', null, $account?->employee_id);
    }

    public static function commentDeleted(Blocker $blocker, ?SystemAccount $account): void
    {
        self::log($blocker, 'comment_deleted', 'Xoá bình luận', null, $account?->employee_id);
    }
}
