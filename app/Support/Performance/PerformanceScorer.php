<?php

namespace App\Support\Performance;

/**
 * Điểm hiệu suất KHÁCH QUAN, tính thuần từ chỉ số task (không phụ thuộc người
 * chấm). Tất cả thang 0–100. Trọng số đọc từ config('performance.scoring').
 */
class PerformanceScorer
{
    /**
     * @param  array{assigned:int, done:int, onTime:int, overdue:int, blocked:int}  $m
     * @return array{performance:int, kpi:int, onTime:int, quality:int, completion:int}
     */
    public function score(array $m): array
    {
        $assigned = max(0, (int) ($m['assigned'] ?? 0));
        $done = max(0, (int) ($m['done'] ?? 0));
        $onTime = max(0, (int) ($m['onTime'] ?? 0));
        $blocked = max(0, (int) ($m['blocked'] ?? 0));

        $completion = $assigned > 0 ? $this->pct($done, $assigned) : 0;
        // Đúng hạn tính trên số task đã hoàn thành (chỉ task xong mới đánh giá được đúng/trễ).
        $onTimeRate = $done > 0 ? $this->pct($onTime, $done) : 0;
        // Chất lượng = phần task KHÔNG bị chặn trên tổng giao (proxy cho độ trơn/làm lại).
        $quality = $assigned > 0 ? 100 - $this->pct($blocked, $assigned) : 100;

        $weights = (array) config('performance.scoring.weights', [
            'completion' => 0.45, 'on_time' => 0.35, 'quality' => 0.20,
        ]);
        $sum = array_sum($weights) ?: 1.0;

        $performance = (int) round(
            ($completion * ($weights['completion'] ?? 0)
            + $onTimeRate * ($weights['on_time'] ?? 0)
            + $quality * ($weights['quality'] ?? 0)) / $sum
        );

        return [
            'performance' => $this->clamp($performance),
            'kpi' => $completion, // KPI = mức hoàn thành mục tiêu được giao
            'onTime' => $onTimeRate,
            'quality' => $this->clamp($quality),
            'completion' => $completion,
        ];
    }

    /** Xếp hạng chữ từ điểm hiệu suất 0–100. */
    public function grade(int $performance): string
    {
        return match (true) {
            $performance >= 90 => 'S',
            $performance >= 80 => 'A',
            $performance >= 65 => 'B',
            $performance >= 50 => 'C',
            default => 'D',
        };
    }

    private function pct(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }

    private function clamp(int $v): int
    {
        return max(0, min(100, $v));
    }
}
