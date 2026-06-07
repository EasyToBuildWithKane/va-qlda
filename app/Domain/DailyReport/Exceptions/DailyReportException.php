<?php

namespace App\Domain\DailyReport\Exceptions;

use DomainException;

class DailyReportException extends DomainException
{
    public static function notWorkingDay(): self
    {
        return new self('Reports can only be submitted on working days (Mon–Sat).');
    }

    public static function notSubmittable(): self
    {
        return new self('Only a draft report can be submitted.');
    }

    public static function notReviewable(): self
    {
        return new self('Only a submitted report can be reviewed.');
    }

    public static function notDeletable(): self
    {
        return new self('Chỉ có thể xoá báo cáo ở trạng thái nháp.');
    }
}
