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

    public function test_full_marks_yield_base_ten_plus_kaizen_bonus(): void
    {
        $result = (new ScoringService)->compute($this->uniform(10));

        $this->assertSame(12.0, $result['total']);
        $this->assertSame(Grade::S, $result['grade']);
    }

    public function test_applies_configured_weights_on_base_only(): void
    {
        // Chỉ task_completion = 10 → base = 10 * (0.30/0.85), không cộng Kaizen
        $result = (new ScoringService)->compute([
            'task_completion' => 10,
            'skill_score' => 0,
            'attitude_score' => 0,
            'kaizen_score' => 0,
            'expertise_score' => 0,
        ]);

        $this->assertSame(round(10 * (0.30 / 0.85), 2), $result['total']);
        $this->assertSame(Grade::D, $result['grade']);
    }

    public function test_kaizen_adds_up_to_two_bonus_points(): void
    {
        $baseOnly = (new ScoringService)->compute([
            'task_completion' => 10,
            'skill_score' => 10,
            'attitude_score' => 10,
            'kaizen_score' => 0,
            'expertise_score' => 10,
        ]);

        $withKaizen = (new ScoringService)->compute([
            'task_completion' => 10,
            'skill_score' => 10,
            'attitude_score' => 10,
            'kaizen_score' => 10,
            'expertise_score' => 10,
        ]);

        $this->assertSame(10.0, $baseOnly['total']);
        $this->assertSame(12.0, $withKaizen['total']);
    }

    /**
     * @dataProvider gradeThresholds
     */
    public function test_grade_thresholds(array $scores, Grade $expected): void
    {
        $this->assertSame($expected, (new ScoringService)->compute($scores)['grade']);
    }

    public static function gradeThresholds(): array
    {
        return [
            'S' => [self::staticUniform(9.0), Grade::S],
            'A' => [self::staticUniform(7.0), Grade::A],
            'B' => [self::staticUniform(5.5), Grade::B],
            'C' => [self::staticUniform(4.5), Grade::C],
            'D' => [self::staticUniform(3.0), Grade::D],
        ];
    }

    private static function staticUniform(float $value): array
    {
        return [
            'task_completion' => $value,
            'skill_score' => $value,
            'attitude_score' => $value,
            'kaizen_score' => $value,
            'expertise_score' => $value,
        ];
    }
}
