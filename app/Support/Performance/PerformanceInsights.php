<?php

namespace App\Support\Performance;

/**
 * Rule engine heuristic — sinh "AI Insights" từ payload PerformanceMetrics đã
 * tính (KHÔNG gọi LLM). Mỗi insight: { level, icon, title, body }. Ngưỡng đọc
 * từ config('performance.insights'). Interface giữ ổn định để sau có thể thay
 * bằng Claude API nếu được duyệt về bảo mật.
 */
class PerformanceInsights
{
    /**
     * @param  array<string, mixed>  $metrics  Payload từ PerformanceMetrics::build()
     * @return list<array{level:string, icon:string, title:string, body:string}>
     */
    public function generate(array $metrics): array
    {
        $cfg = (array) config('performance.insights');
        $people = collect($metrics['people'] ?? []);
        $headline = (array) ($metrics['headline'] ?? []);
        $insights = [];

        if ($people->isEmpty()) {
            return [[
                'level' => 'info',
                'icon' => 'info',
                'title' => 'Chưa đủ dữ liệu',
                'body' => 'Không có công việc nào trong phạm vi và khoảng thời gian đang chọn.',
            ]];
        }

        // 1. Hiệu suất tổng thể của nhóm.
        $avg = (int) ($headline['avgScore'] ?? 0);
        $insights[] = [
            'level' => $avg >= 80 ? 'success' : ($avg >= 50 ? 'info' : 'warning'),
            'icon' => 'talent-score',
            'title' => 'Hiệu suất trung bình nhóm: '.$avg.'%',
            'body' => $avg >= 80
                ? 'Nhóm đang duy trì hiệu suất tốt. Tỷ lệ hoàn thành '.($headline['completionRate'] ?? 0).'%, đúng hạn '.($headline['onTimeRate'] ?? 0).'%.'
                : 'Cần chú ý: tỷ lệ hoàn thành '.($headline['completionRate'] ?? 0).'%, đúng hạn '.($headline['onTimeRate'] ?? 0).'%. Xem các thành viên điểm thấp bên dưới.',
        ];

        // 2. Quá tải / nguy cơ trễ.
        $overloaded = $people->where('load', 'overloaded')->sortByDesc('openTasks')->values();
        if ($overloaded->isNotEmpty()) {
            $top = $overloaded->first();
            $names = $overloaded->take(3)->pluck('name')->implode(', ');
            $insights[] = [
                'level' => 'warning',
                'icon' => 'alert',
                'title' => 'Cảnh báo quá tải: '.$overloaded->count().' thành viên',
                'body' => $names.' đang giữ nhiều việc mở (cao nhất: '.$top['name'].' với '.$top['openTasks'].' task). Cân nhắc tái phân bổ nguồn lực.',
            ];
        }

        // 3. Thành viên nhiều task quá hạn → nguy cơ trễ deadline.
        $overdueThreshold = (int) ($cfg['overdue_alert'] ?? 3);
        $lateRisk = $people->filter(fn ($p) => ($p['overdue'] ?? 0) >= $overdueThreshold)
            ->sortByDesc('overdue')->values();
        if ($lateRisk->isNotEmpty()) {
            $top = $lateRisk->first();
            $insights[] = [
                'level' => 'warning',
                'icon' => 'clock',
                'title' => 'Nguy cơ trễ deadline',
                'body' => $top['name'].' có '.$top['overdue'].' task quá hạn chưa hoàn thành'
                    .($lateRisk->count() > 1 ? ' (và '.($lateRisk->count() - 1).' người khác)' : '').'. Cần can thiệp sớm.',
            ];
        }

        // 4. Điểm sáng — đúng hạn cao.
        $goodPct = (int) ($cfg['on_time_good_pct'] ?? 90);
        $star = $people->filter(fn ($p) => ($p['done'] ?? 0) > 0 && ($p['onTimeRate'] ?? 0) >= $goodPct)
            ->sortByDesc('onTimeRate')->sortByDesc('done')->values();
        if ($star->isNotEmpty()) {
            $top = $star->first();
            $insights[] = [
                'level' => 'success',
                'icon' => 'streak',
                'title' => 'Điểm sáng hiệu suất',
                'body' => $top['name'].' đạt '.$top['onTimeRate'].'% đúng hạn với '.$top['done'].' task hoàn thành. Ghi nhận đóng góp nổi bật.',
            ];
        }

        // 5. Hiệu suất thấp.
        $lowScore = (int) ($cfg['low_score'] ?? 50);
        $weak = $people->filter(fn ($p) => ($p['committed'] ?? 0) > 0 && ($p['score'] ?? 0) < $lowScore)
            ->sortBy('score')->values();
        if ($weak->isNotEmpty()) {
            $top = $weak->first();
            $insights[] = [
                'level' => 'warning',
                'icon' => 'performance',
                'title' => 'Hiệu suất cần cải thiện',
                'body' => $weak->count().' thành viên dưới ngưỡng '.$lowScore.'% (thấp nhất: '.$top['name'].' — '.$top['score'].'%). Nên trao đổi 1:1 và xem lại khối lượng giao việc.',
            ];
        }

        // 6. Một dự án chiếm phần lớn workload nhóm.
        $contribution = collect($metrics['projectContribution'] ?? []);
        $totalTasks = $contribution->sum('total');
        if ($totalTasks > 0 && $contribution->isNotEmpty()) {
            $top = $contribution->first();
            $share = (int) round(($top['total'] / $totalTasks) * 100);
            $dominance = (int) ($cfg['project_dominance_pct'] ?? 50);
            if ($share >= $dominance) {
                $insights[] = [
                    'level' => 'info',
                    'icon' => 'projects',
                    'title' => 'Tập trung nguồn lực',
                    'body' => 'Dự án "'.$top['name'].'" chiếm '.$share.'% khối lượng công việc của nhóm trong kỳ. Đảm bảo các dự án khác không bị bỏ trống.',
                ];
            }
        }

        return $insights;
    }
}
