<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Domain\DailyReport\Services\ScoringService;
use App\Support\DailyReport\DailyReportScoringResolver;
use App\Support\Enums\ReportStatus;
use Illuminate\Support\Arr;

class ScoreReportUseCase
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly DailyReportReviewTelegramNotifier $reviewTelegram,
        private readonly DailyReportScoringResolver $rubricResolver,
    ) {}

    /**
     * Score a submitted report: compute total/grade, persist the score and
     * lock the report as reviewed.
     *
     * @param  array<string, float|int>  $scores
     *
     * @throws DailyReportException
     */
    public function execute(DailyReport $report, int $reviewerId, array $scores, ?string $notes = null): DailyReportScore
    {
        if ($report->status !== ReportStatus::Submitted) {
            throw DailyReportException::notReviewable();
        }

        $report->loadMissing('employee');
        $rubric = $this->rubricResolver->forEmployee($report->employee);
        $computed = $this->scoring->compute($scores, $rubric);

        $dimensions = Arr::only($scores, [
            'task_completion',
            'skill_score',
            'attitude_score',
            'kaizen_score',
            'expertise_score',
        ]);

        $score = $report->score()->updateOrCreate(
            ['report_id' => $report->id],
            [
                ...$dimensions,
                'total_score' => $computed['total'],
                'grade' => $computed['grade'],
                'reviewer_id' => $reviewerId,
                'notes' => $notes,
                'scoring_snapshot' => $this->rubricResolver->toSnapshot($rubric),
            ],
        );

        $report->forceFill([
            'status' => ReportStatus::Reviewed,
            'reviewed_at' => now(),
        ])->save();

        activity('daily_report')
            ->performedOn($report)
            ->event('reviewed')
            ->withProperties(['grade' => $computed['grade']->value, 'total' => $computed['total']])
            ->log('Daily report reviewed');

        $this->reviewTelegram->notifyReviewed($report, $score);

        return $score;
    }
}
