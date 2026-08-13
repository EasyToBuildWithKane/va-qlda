<?php

namespace App\Support\WeeklyReport;

use App\Models\Sprint;
use Illuminate\Support\Carbon;

/**
 * Sprint không có khái niệm "tuần" → dẫn xuất tuần bằng cách chia khoảng
 * start_date..end_date thành các bucket 7 ngày (bắt đầu từ Thứ 2 của tuần chứa
 * ngày bắt đầu Sprint). Không có Sprint/ngày → fallback tuần ISO hiện tại.
 */
class SprintWeekResolver
{
    /**
     * @return array<int, array{week_number:int, start:Carbon, end:Carbon}>
     */
    public function weeks(?Sprint $sprint): array
    {
        if (! $sprint || ! $sprint->start_date) {
            return [$this->currentIsoWeek()];
        }

        $cursor = $sprint->start_date->copy()->startOfWeek(Carbon::MONDAY);
        $end = ($sprint->end_date ?? $sprint->start_date->copy()->addWeeks(3))
            ->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $number = 1;
        while ($cursor->lte($end) && $number <= 52) {
            $weeks[] = [
                'week_number' => $number,
                'start' => $cursor->copy()->startOfDay(),
                'end' => $cursor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
            ];
            $cursor->addWeek();
            $number++;
        }

        return $weeks;
    }

    /**
     * Tuần hiện tại (chứa hôm nay), clamp trong khoảng Sprint.
     *
     * @return array{week_number:int, start:Carbon, end:Carbon}
     */
    public function currentWeek(?Sprint $sprint): array
    {
        $weeks = $this->weeks($sprint);
        $today = Carbon::today();

        foreach ($weeks as $week) {
            if ($today->betweenIncluded($week['start'], $week['end'])) {
                return $week;
            }
        }

        // Trước Sprint → tuần đầu; sau Sprint → tuần cuối.
        if ($today->lt($weeks[0]['start'])) {
            return $weeks[0];
        }

        return $weeks[array_key_last($weeks)];
    }

    /** @return array{week_number:int, start:Carbon, end:Carbon} */
    public function weekByNumber(?Sprint $sprint, int $weekNumber): array
    {
        foreach ($this->weeks($sprint) as $week) {
            if ($week['week_number'] === $weekNumber) {
                return $week;
            }
        }

        return $this->currentWeek($sprint);
    }

    /**
     * Tuần lịch T2–CN chứa $day (mặc định hôm nay) — không kẹp theo ngày Sprint.
     *
     * @return array{week_number:int, start:Carbon, end:Carbon}
     */
    public function calendarWeek(?Carbon $day = null): array
    {
        $start = ($day ?? Carbon::today())->copy()->startOfWeek(Carbon::MONDAY);

        return [
            'week_number' => (int) $start->isoWeek(),
            'start' => $start->copy()->startOfDay(),
            'end' => $start->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
        ];
    }

    /** @return array{week_number:int, start:Carbon, end:Carbon} */
    private function currentIsoWeek(): array
    {
        return $this->calendarWeek();
    }
}
