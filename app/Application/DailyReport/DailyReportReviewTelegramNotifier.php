<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Services\Telegram\TelegramBotService;

class DailyReportReviewTelegramNotifier
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly DailyReportReviewTelegramFormatter $formatter,
    ) {}

    public function notifyReviewed(DailyReport $report, DailyReportScore $score): void
    {
        if (! config('telegram.daily_report_review')) {
            return;
        }

        $this->telegram->sendMessage($this->formatter->format($report, $score));
    }

    public function notifyRejected(DailyReport $report, int $reviewerEmployeeId, string $notes): void
    {
        if (! config('telegram.daily_report_review')) {
            return;
        }

        $this->telegram->sendMessage(
            $this->formatter->formatRejected($report, $reviewerEmployeeId, $notes),
        );
    }
}
