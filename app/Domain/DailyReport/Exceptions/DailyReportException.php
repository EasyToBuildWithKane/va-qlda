<?php

namespace App\Domain\DailyReport\Exceptions;

use DomainException;

class DailyReportException extends DomainException
{
    public static function notWorkingDay(): self
    {
        return new self('Chỉ được nộp báo cáo vào ngày làm việc (Thứ Hai – Thứ Bảy).');
    }

    public static function notSubmittable(): self
    {
        return new self('Chỉ có thể nộp báo cáo đang ở trạng thái nháp.');
    }

    public static function notReviewable(): self
    {
        return new self('Chỉ có thể duyệt báo cáo đã được nộp.');
    }

    public static function notDeletable(): self
    {
        return new self('Chỉ có thể xoá báo cáo ở trạng thái nháp.');
    }

    public static function cannotRecall(): self
    {
        return new self('Chỉ có thể rút lại báo cáo đang chờ duyệt.');
    }

    public static function recallExpired(): self
    {
        return new self('Chỉ được rút lại báo cáo trong ngày làm việc. Nếu cần chỉnh sửa sau đó, hãy nhờ người duyệt trả lại.');
    }

    public static function projectNotAllowed(string $projectName): self
    {
        return new self("Bạn không có quyền ghi task vào dự án «{$projectName}».");
    }
}
