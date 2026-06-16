<?php

namespace Tests\Unit;

use App\Support\Performance\PerformanceScorer;
use Tests\TestCase;

class PerformanceScorerTest extends TestCase
{
    public function test_all_done_on_time_no_blocked_is_full_score(): void
    {
        $s = (new PerformanceScorer)->score([
            'assigned' => 10, 'done' => 10, 'onTime' => 10, 'overdue' => 0, 'blocked' => 0,
        ]);

        $this->assertSame(100, $s['completion']);
        $this->assertSame(100, $s['onTime']);
        $this->assertSame(100, $s['quality']);
        $this->assertSame(100, $s['performance']);
        $this->assertSame('S', (new PerformanceScorer)->grade($s['performance']));
    }

    public function test_no_assigned_tasks_yields_zero_performance_full_quality(): void
    {
        $s = (new PerformanceScorer)->score([
            'assigned' => 0, 'done' => 0, 'onTime' => 0, 'overdue' => 0, 'blocked' => 0,
        ]);

        $this->assertSame(0, $s['completion']);
        $this->assertSame(0, $s['onTime']);
        $this->assertSame(100, $s['quality']); // không có task → không trừ chất lượng
        $this->assertSame(20, $s['performance']); // chỉ trọng số quality (0.20) đóng góp
    }

    public function test_weighted_blend_matches_config(): void
    {
        // 8/10 xong, 6/8 đúng hạn, 2/10 blocked
        $s = (new PerformanceScorer)->score([
            'assigned' => 10, 'done' => 8, 'onTime' => 6, 'overdue' => 1, 'blocked' => 2,
        ]);

        $completion = 80;            // 8/10
        $onTime = 75;                // 6/8
        $quality = 100 - 20;         // 1 - 2/10
        $expected = (int) round($completion * 0.45 + $onTime * 0.35 + $quality * 0.20);

        $this->assertSame($completion, $s['completion']);
        $this->assertSame($onTime, $s['onTime']);
        $this->assertSame($quality, $s['quality']);
        $this->assertSame($expected, $s['performance']);
    }

    public function test_grade_thresholds(): void
    {
        $scorer = new PerformanceScorer;

        $this->assertSame('S', $scorer->grade(95));
        $this->assertSame('A', $scorer->grade(82));
        $this->assertSame('B', $scorer->grade(70));
        $this->assertSame('C', $scorer->grade(55));
        $this->assertSame('D', $scorer->grade(40));
    }
}
