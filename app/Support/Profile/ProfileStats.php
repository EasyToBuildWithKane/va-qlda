<?php

namespace App\Support\Profile;

use App\Models\Employee;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\Enums\TaskStatus;

/**
 * Derives the real, data-backed metrics shown on the employee portfolio
 * (hero tiles, competency radar, achievements, rule-based suggestions).
 *
 * Hard rule: NO fabricated numbers. Everything below is computed from data that
 * already exists (profile fields, skill levels, tasks, worklogs, projects).
 * Metrics with no source (performance index, attendance, KPI) are intentionally
 * absent rather than invented.
 */
class ProfileStats
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Employee $employee): array
    {
        $meta = is_array($employee->meta) ? $employee->meta : [];
        $details = is_array($meta['skill_details'] ?? null) ? $meta['skill_details'] : [];

        $radar = self::skillRadar($employee->skills, $details);
        $skillScore = self::skillScore($details);

        $taskAgg = Task::query()
            ->where('assignee_id', $employee->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', [TaskStatus::Done->value])
            ->first();

        $tasksTotal = (int) ($taskAgg->total ?? 0);
        $tasksDone = (int) ($taskAgg->done ?? 0);

        return [
            'profile_completion' => self::completion($employee, $meta),
            'skill_score' => $skillScore,
            'rated_skills' => self::ratedSkillCount($details),
            'total_skills' => is_array($employee->skills) ? count($employee->skills) : 0,
            'tenure' => self::tenure($employee),
            'projects_total' => self::projectsTotal($employee),
            'tasks_total' => $tasksTotal,
            'tasks_done' => $tasksDone,
            'task_completion' => $tasksTotal > 0 ? (int) round($tasksDone / $tasksTotal * 100) : null,
            'worklog_hours' => (float) Worklog::query()->where('employee_id', $employee->id)->sum('hours'),
            'skill_radar' => $radar,
            'insights' => self::insights($radar, $details, $skillScore),
        ];
    }

    /** Percentage of key profile fields that are filled in. */
    private static function completion(Employee $employee, array $meta): int
    {
        $socials = is_array($meta['socials'] ?? null) ? array_filter($meta['socials']) : [];
        $checks = [
            (string) $employee->avatar_path !== '',
            (string) $employee->role_title !== '',
            (string) $employee->phone !== '',
            (string) $employee->email !== '',
            (string) ($meta['location'] ?? '') !== '',
            (string) ($meta['bio'] ?? '') !== '',
            $employee->join_date !== null,
            is_array($employee->skills) && $employee->skills !== [],
            $socials !== [],
        ];

        $filled = count(array_filter($checks));

        return (int) round($filled / count($checks) * 100);
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private static function skillScore(array $details): ?int
    {
        $levels = self::levels($details);

        return $levels === [] ? null : (int) round(array_sum($levels) / count($levels) / 5 * 100);
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return list<int>
     */
    private static function levels(array $details): array
    {
        $levels = [];
        foreach ($details as $d) {
            if (is_array($d) && isset($d['level']) && is_numeric($d['level'])) {
                $levels[] = max(1, min(5, (int) $d['level']));
            }
        }

        return $levels;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    private static function ratedSkillCount(array $details): int
    {
        return count(self::levels($details));
    }

    /**
     * Average competency score (0–100) per domain, for the radar chart.
     *
     * @param  array<int, string>|null  $skills
     * @param  array<int, array<string, mixed>>  $details
     * @return list<array{key:string, label:string, score:int, count:int}>
     */
    private static function skillRadar(?array $skills, array $details): array
    {
        $built = SkillCatalog::build($skills, $details);

        $byKey = [];
        foreach ($built['groups'] as $group) {
            $levels = [];
            foreach ($group['items'] as $item) {
                if (($item['percent'] ?? null) !== null) {
                    $levels[] = (int) $item['percent'];
                }
            }
            $byKey[$group['key']] = [
                'count' => count($group['items']),
                'score' => $levels === [] ? 0 : (int) round(array_sum($levels) / count($levels)),
            ];
        }

        $radar = [];
        foreach (SkillCatalog::categoryLabels() as $key => $label) {
            $radar[] = [
                'key' => $key,
                'label' => $label,
                'score' => $byKey[$key]['score'] ?? 0,
                'count' => $byKey[$key]['count'] ?? 0,
            ];
        }

        return $radar;
    }

    /**
     * @return array{months:int, years:int, label:string}|null
     */
    private static function tenure(Employee $employee): ?array
    {
        if ($employee->join_date === null) {
            return null;
        }

        $months = (int) $employee->join_date->diffInMonths(now());
        $years = intdiv($months, 12);
        $rem = $months % 12;

        $label = match (true) {
            $months < 1 => 'Dưới 1 tháng',
            $years > 0 && $rem > 0 => "{$years} năm {$rem} tháng",
            $years > 0 => "{$years} năm",
            default => "{$rem} tháng",
        };

        return ['months' => $months, 'years' => $years, 'label' => $label];
    }

    private static function projectsTotal(Employee $employee): int
    {
        $member = $employee->projects()
            ->tap(fn ($q) => Employee::applyActiveProjectPivotFilter($q))
            ->pluck('projects.id');
        $managed = $employee->managedProjects()->pluck('id');

        return $member->merge($managed)->unique()->count();
    }

    /**
     * Rule-based, real-data suggestions (not an LLM, no fabricated comparisons).
     *
     * @param  list<array{key:string, label:string, score:int, count:int}>  $radar
     * @param  array<int, array<string, mixed>>  $details
     * @return list<array{icon:string, tone:string, text:string}>
     */
    private static function insights(array $radar, array $details, ?int $skillScore): array
    {
        $out = [];

        $rated = array_values(array_filter($radar, fn ($r) => $r['count'] > 0 && $r['score'] > 0));
        usort($rated, fn ($a, $b) => $b['score'] <=> $a['score']);

        if ($rated !== []) {
            $top = $rated[0];
            $out[] = [
                'icon' => 'talent-score',
                'tone' => 'emerald',
                'text' => "Thế mạnh nổi bật: {$top['label']} (điểm trung bình {$top['score']}/100).",
            ];
        }

        $gaps = array_values(array_filter($radar, fn ($r) => $r['count'] === 0));
        if (count($gaps) > 0 && count($gaps) < count($radar)) {
            $labels = array_slice(array_map(fn ($g) => $g['label'], $gaps), 0, 3);
            $out[] = [
                'icon' => 'career',
                'tone' => 'sky',
                'text' => 'Lĩnh vực chưa có kỹ năng: '.implode(', ', $labels).'. Bổ sung để mở rộng năng lực.',
            ];
        }

        $certified = count(array_filter($details, fn ($d) => is_array($d) && ! empty($d['certified'])));
        if ($certified > 0) {
            $out[] = [
                'icon' => 'certified',
                'tone' => 'violet',
                'text' => "{$certified} kỹ năng đã có chứng chỉ xác thực.",
            ];
        }

        $unrated = count(array_filter(
            $details,
            fn ($d) => is_array($d) && (! isset($d['level']) || ! is_numeric($d['level'])),
        ));
        if ($skillScore === null) {
            $out[] = [
                'icon' => 'sparkles',
                'tone' => 'amber',
                'text' => 'Chấm mức độ cho kỹ năng để dựng biểu đồ năng lực và điểm kỹ năng.',
            ];
        } elseif ($unrated > 0) {
            $out[] = [
                'icon' => 'sparkles',
                'tone' => 'amber',
                'text' => "{$unrated} kỹ năng chưa chấm mức độ — cập nhật để hoàn thiện hồ sơ.",
            ];
        }

        return $out;
    }
}
