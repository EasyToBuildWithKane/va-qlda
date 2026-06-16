<?php

namespace App\Support\Performance;

use Illuminate\Support\Carbon;

/**
 * Chia kỳ audit thành các bucket con (tuần / tháng) — dùng timeline và danh sách audit.
 */
class PerformancePeriodBuckets
{
    /**
     * @return list<array{key:string, label:string, start:Carbon, end:Carbon}>
     */
    public static function forFilter(PerformanceFilter $filter): array
    {
        $byMonth = $filter->periodType === 'year';

        $buckets = [];
        $cursor = $filter->start->copy();
        $guard = 0;

        while ($cursor->lte($filter->end) && $guard++ < 60) {
            if ($byMonth) {
                $start = $cursor->copy()->startOfMonth();
                $end = $cursor->copy()->endOfMonth();
                $label = 'Tháng '.$cursor->format('m/Y');
                $cursor->addMonthNoOverflow()->startOfMonth();
            } else {
                $start = $cursor->copy()->startOfWeek();
                $end = $cursor->copy()->endOfWeek();
                $label = 'Tuần '.$start->format('W');
                $cursor->addWeek()->startOfWeek();
            }

            $buckets[] = [
                'key' => $start->toDateString(),
                'label' => $label,
                'start' => $start->lt($filter->start) ? $filter->start->copy() : $start,
                'end' => $end->gt($filter->end) ? $filter->end->copy() : $end,
            ];
        }

        return array_reverse($buckets);
    }
}
