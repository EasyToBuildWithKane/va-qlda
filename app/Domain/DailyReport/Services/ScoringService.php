<?php

namespace App\Domain\DailyReport\Services;

use App\Domain\DailyReport\Models\DailyReportScore;
use App\Support\Enums\Grade;
use App\Support\Enums\ScoreTrend;
use Carbon\CarbonInterface;

/**
 * Pure scoring logic for daily reports: weighted total, grade mapping and the
 * week-over-week trend. Weights/thresholds come from config('daily_report').
 */
class ScoringService
{
    /**
     * Compute the weighted total and grade from the five dimensions.
     *
     * @param  array<string, float|int|string>  $scores
     * @return array{total: float, grade: Grade}
     */
    public function compute(array $scores): array
    {
        $weights = config('daily_report.weights');
        $weightSum = array_sum($weights) ?: 1.0;

        $base = 0.0;
        foreach ($weights as $key => $weight) {
            $base += (float) ($scores[$key] ?? 0) * ((float) $weight / $weightSum);
        }

        $kaizen = (float) ($scores['kaizen_score'] ?? 0);
        $bonusMax = (float) config('daily_report.kaizen_bonus_max', 2.0);
        $bonus = ($kaizen / 10) * $bonusMax;

        $total = round($base + $bonus, 2);

        return [
            'total' => $total,
            'grade' => Grade::fromScore($total),
        ];
    }

    /**
     * Classify this week's total against the employee's previous-week average.
     */
    public function trend(int $employeeId, CarbonInterface $weekRef, float $currentTotal): ScoreTrend
    {
        $start = $weekRef->copy()->subWeek()->startOfWeek();
        $end = $weekRef->copy()->subWeek()->endOfWeek();

        $previousAvg = DailyReportScore::query()
            ->whereHas('report', fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$start, $end]))
            ->avg('total_score');

        if ($previousAvg === null) {
            return ScoreTrend::Stable;
        }

        $tolerance = (float) config('daily_report.trend_tolerance');

        return match (true) {
            $currentTotal > $previousAvg + $tolerance => ScoreTrend::Up,
            $currentTotal < $previousAvg - $tolerance => ScoreTrend::Down,
            default => ScoreTrend::Stable,
        };
    }
}
