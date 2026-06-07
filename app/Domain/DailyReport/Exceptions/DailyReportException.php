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
}
