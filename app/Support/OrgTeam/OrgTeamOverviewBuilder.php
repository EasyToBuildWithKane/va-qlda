<?php

namespace App\Support\OrgTeam;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;

/**
 * Real, derivable aggregate stats for the org-teams dashboard header.
 *
 * Deliberately contains NO fabricated metrics (KPI / budget / HR cost) — the
 * data model has no source for those. Everything here is computed from teams,
 * memberships and the employees' own skill levels.
 */
class OrgTeamOverviewBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $teams = OrgTeam::query()->get(['id', 'level', 'leader_id', 'is_active']);

        $rootsTotal = $teams->where('level', 1)->count();
        $subteamsTotal = $teams->where('level', '>', 1)->count();
        $teamsTotal = $teams->count();
        $activeTeams = $teams->where('is_active', true)->count();

        // Unique people on the chart = team leaders ∪ team/section members.
        $leaderIds = $teams->pluck('leader_id')->filter()->unique();
        $memberIds = OrgTeamMember::query()
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->unique();
        $employeeIds = $leaderIds->merge($memberIds)->unique()->values();

        $employees = $employeeIds->isEmpty()
            ? collect()
            : Employee::query()
                ->whereIn('id', $employeeIds->all())
                ->get(['id', 'is_active', 'meta']);

        $peopleTotal = $employees->count();
        $activePeople = $employees->where('is_active', true)->count();

        // Avg skill score across people who actually have leveled skills.
        $scores = $employees
            ->map(fn (Employee $e) => self::skillScore($e))
            ->filter(fn ($s) => $s !== null);

        return self::overviewPayload($peopleTotal, $activePeople, $teams, $leaderIds, $scores);
    }

    /**
     * Thống kê chỉ từ cây sơ đồ đã resolve (landing /congnghe) — không đếm toàn bộ nhân sự hệ thống.
     *
     * @param  array<int, array<string, mixed>>  $forest
     * @return array<string, mixed>
     */
    public static function buildFromForest(array $forest): array
    {
        $leaderIds = collect();
        $memberIds = collect();
        $teamsTotal = 0;
        $rootsTotal = 0;
        $subteamsTotal = 0;
        $activeTeams = 0;

        $walk = function (array $node, bool $isRoot) use (
            &$walk,
            &$leaderIds,
            &$memberIds,
            &$teamsTotal,
            &$rootsTotal,
            &$subteamsTotal,
            &$activeTeams,
        ): void {
            $teamsTotal++;
            if ($isRoot) {
                $rootsTotal++;
            } else {
                $subteamsTotal++;
            }
            if (($node['is_active'] ?? true) !== false) {
                $activeTeams++;
            }

            $leader = is_array($node['leader'] ?? null) ? $node['leader'] : null;
            if (! empty($leader['id'])) {
                $leaderIds->push((int) $leader['id']);
            }

            $members = $node['members'] ?? [];
            if (is_array($members)) {
                foreach ($members as $row) {
                    $emp = is_array($row) ? ($row['employee'] ?? null) : null;
                    if (is_array($emp) && ! empty($emp['id'])) {
                        $memberIds->push((int) $emp['id']);
                    }
                }
            }

            $children = $node['children'] ?? [];
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $walk($child, false);
                    }
                }
            }
        };

        foreach ($forest as $root) {
            if (is_array($root)) {
                $walk($root, true);
            }
        }

        $employeeIds = $leaderIds->merge($memberIds)->unique()->values();
        $employees = $employeeIds->isEmpty()
            ? collect()
            : Employee::query()
                ->whereIn('id', $employeeIds->all())
                ->get(['id', 'is_active', 'meta']);

        $peopleTotal = $employees->count();
        $activePeople = $employees->where('is_active', true)->count();

        $scores = $employees
            ->map(fn (Employee $e) => self::skillScore($e))
            ->filter(fn ($s) => $s !== null);

        $teams = collect([
            (object) ['level' => 1, 'leader_id' => null, 'is_active' => true],
        ]);

        return self::overviewPayload(
            $peopleTotal,
            $activePeople,
            $teams,
            $leaderIds->unique(),
            $scores,
            [
                'teams_total' => $teamsTotal,
                'roots_total' => $rootsTotal,
                'subteams_total' => $subteamsTotal,
                'active_teams' => $activeTeams,
                'leaders_total' => $leaderIds->unique()->count(),
            ],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $teams
     * @param  \Illuminate\Support\Collection<int, mixed>  $leaderIds
     * @param  \Illuminate\Support\Collection<int, int>  $scores
     * @param  array<string, int|null>  $overrides
     * @return array<string, mixed>
     */
    private static function overviewPayload(
        int $peopleTotal,
        int $activePeople,
        $teams,
        $leaderIds,
        $scores,
        array $overrides = [],
    ): array {
        $rootsTotal = $overrides['roots_total'] ?? $teams->where('level', 1)->count();
        $subteamsTotal = $overrides['subteams_total'] ?? $teams->where('level', '>', 1)->count();
        $teamsTotal = $overrides['teams_total'] ?? $teams->count();
        $activeTeams = $overrides['active_teams'] ?? $teams->where('is_active', true)->count();
        $leadersTotal = $overrides['leaders_total'] ?? $leaderIds->count();

        return [
            'people_total' => $peopleTotal,
            'active_people' => $activePeople,
            'active_ratio' => $peopleTotal > 0 ? (int) round($activePeople / $peopleTotal * 100) : 0,
            'teams_total' => $teamsTotal,
            'roots_total' => $rootsTotal,
            'subteams_total' => $subteamsTotal,
            'active_teams' => $activeTeams,
            'leaders_total' => $leadersTotal,
            'avg_skill_score' => $scores->isNotEmpty() ? (int) round($scores->avg()) : null,
            'rated_people' => $scores->count(),
        ];
    }

    /**
     * Mean of an employee's leveled skills (1–5) expressed as a 0–100 score,
     * or null when no leveled skill data exists.
     */
    private static function skillScore(Employee $employee): ?int
    {
        $meta = is_array($employee->meta) ? $employee->meta : [];
        $details = $meta['skill_details'] ?? null;
        if (! is_array($details) || $details === []) {
            return null;
        }

        $levels = [];
        foreach ($details as $detail) {
            if (is_array($detail) && isset($detail['level']) && is_numeric($detail['level'])) {
                $levels[] = max(1, min(5, (int) $detail['level']));
            }
        }

        if ($levels === []) {
            return null;
        }

        return (int) round(array_sum($levels) / count($levels) / 5 * 100);
    }
}
