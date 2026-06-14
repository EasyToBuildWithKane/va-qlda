<?php

namespace App\Support\Profile;

use App\Models\Employee;

/**
 * Composite "Talent Score" (0–100) built from five sub-scores, each derived
 * from a different real signal. Components with no data are excluded from the
 * average rather than guessed, so the score reflects what's actually known.
 */
class TalentScore
{
    /**
     * @return array{total:int|null, components: list<array{key:string, label:string, score:int|null, color:string}>}
     */
    public static function for(Employee $employee): array
    {
        $components = [
            ['key' => 'skill', 'label' => 'Kỹ năng', 'score' => self::skillScore($employee), 'color' => 'sky'],
            ['key' => 'kpi', 'label' => 'KPI', 'score' => self::kpiScore($employee), 'color' => 'emerald'],
            ['key' => 'teamwork', 'label' => 'Làm việc nhóm', 'score' => self::ratingScore($employee, 'rating_teamwork'), 'color' => 'violet'],
            ['key' => 'leadership', 'label' => 'Lãnh đạo', 'score' => self::ratingScore($employee, 'rating_leadership'), 'color' => 'rose'],
            ['key' => 'learning', 'label' => 'Học tập', 'score' => self::learningScore($employee), 'color' => 'amber'],
        ];

        $known = array_filter(array_column($components, 'score'), fn ($s) => $s !== null);
        $total = $known !== [] ? (int) round(array_sum($known) / count($known)) : null;

        return ['total' => $total, 'components' => $components];
    }

    private static function skillScore(Employee $employee): ?int
    {
        $levels = $employee->skillEntries->pluck('level')->filter(fn ($l) => $l > 0);
        if ($levels->isEmpty()) {
            return null;
        }

        return (int) round($levels->avg() / 5 * 100);
    }

    private static function kpiScore(Employee $employee): ?int
    {
        $kpis = $employee->kpis->filter(fn ($k) => $k->attainment() !== null);
        if ($kpis->isEmpty()) {
            return null;
        }

        $weightSum = $kpis->sum('weight') ?: $kpis->count();
        $weighted = $kpis->sum(fn ($k) => $k->attainment() * max($k->weight, 1));

        return (int) round(min($weighted / $weightSum, 1.0) * 100);
    }

    private static function ratingScore(Employee $employee, string $dimension): ?int
    {
        $ratings = $employee->reviewsReceived->pluck($dimension)->filter(fn ($r) => $r !== null && $r > 0);
        if ($ratings->isEmpty()) {
            return null;
        }

        return (int) round($ratings->avg() / 5 * 100);
    }

    private static function learningScore(Employee $employee): ?int
    {
        $items = $employee->learningItems->whereIn('status', [
            \App\Support\Enums\LearningStatus::Completed,
            \App\Support\Enums\LearningStatus::InProgress,
        ]);
        if ($items->isEmpty()) {
            return null;
        }

        return (int) round($items->avg('progress'));
    }
}
