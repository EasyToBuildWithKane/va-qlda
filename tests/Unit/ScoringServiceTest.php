<?php

namespace Tests\Unit;

use App\Domain\DailyReport\Services\ScoringService;
use App\Support\Enums\Grade;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    private function uniform(float $value): array
    {
        return [
            'task_completion' => $value,
            'skill_score' => $value,
            'attitude_score' => $value,
            'kaizen_score' => $value,
            'expertise_score' => $value,
        ];
    }

    public function test_weighted_total_sums_to_input_when_uniform(): void
    {
        $result = (new ScoringService)->compute($this->uniform(10));

        $this->assertSame(10.0, $result['total']);
        $this->assertSame(Grade::S, $result['grade']);
    }

    public function test_applies_configured_weights(): void
    {
        // task_completion has weight 0.30; everything else 0 → 10 * 0.30 = 3.0
        $result = (new ScoringService)->compute([
            'task_completion' => 10,
            'skill_score' => 0,
            'attitude_score' => 0,
            'kaizen_score' => 0,
            'expertise_score' => 0,
        ]);

        $this->assertSame(3.0, $result['total']);
        $this->assertSame(Grade::D, $result['grade']);
    }

    /**
     * @dataProvider gradeThresholds
     */
    public function test_grade_thresholds(float $value, Grade $expected): void
    {
        $this->assertSame($expected, (new ScoringService)->compute($this->uniform($value))['grade']);
    }

    public static function gradeThresholds(): array
    {
        return [
            'S' => [9.0, Grade::S],
            'A' => [8.0, Grade::A],
            'B' => [6.5, Grade::B],
            'C' => [5.0, Grade::C],
            'D' => [4.9, Grade::D],
        ];
    }
}
