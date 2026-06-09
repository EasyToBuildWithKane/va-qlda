<?php

namespace App\Support;

use App\Models\ProjectAttachment;
use App\Models\ProjectAttachmentActivity;
use App\Models\SystemAccount;

class ProjectAttachmentActivityLogger
{
    public static function log(
        ProjectAttachment $attachment,
        string $event,
        string $description,
        ?array $meta = null,
        ?int $employeeId = null,
    ): void {
        ProjectAttachmentActivity::create([
            'project_attachment_id' => $attachment->id,
            'employee_id' => $employeeId,
            'event' => $event,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

    public static function linkAdded(ProjectAttachment $attachment, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'link_added',
            'Thêm link Google: '.$attachment->original_name,
            [
                'file' => $attachment->original_name,
                'url' => $attachment->external_url,
                'category' => $attachment->category->value,
            ],
            $account?->employee_id,
        );
    }

    public static function linkUpdated(ProjectAttachment $attachment, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'link_updated',
            'Cập nhật link Google: '.$attachment->original_name,
            [
                'file' => $attachment->original_name,
                'url' => $attachment->external_url,
            ],
            $account?->employee_id,
        );
    }

    public static function uploaded(ProjectAttachment $attachment, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'uploaded',
            'Tải lên: '.$attachment->original_name,
            [
                'file' => $attachment->original_name,
                'size' => $attachment->size,
                'category' => $attachment->category->value,
            ],
            $account?->employee_id,
        );
    }

    public static function notesUpdated(ProjectAttachment $attachment, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'note_updated',
            'Cập nhật ghi chú tài liệu',
            ['file' => $attachment->original_name],
            $account?->employee_id,
        );
    }

    public static function replaced(ProjectAttachment $attachment, string $oldName, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'replaced',
            "Thay thế file: {$oldName} → {$attachment->original_name}",
            ['from' => $oldName, 'to' => $attachment->original_name],
            $account?->employee_id,
        );
    }

    public static function deleted(ProjectAttachment $attachment, ?SystemAccount $account): void
    {
        self::log(
            $attachment,
            'deleted',
            'Xoá tài liệu: '.$attachment->original_name,
            ['file' => $attachment->original_name],
            $account?->employee_id,
        );
    }
}
