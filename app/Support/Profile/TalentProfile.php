<?php

namespace App\Support\Profile;

use App\Models\CareerLevel;
use App\Models\Employee;
use App\Models\FeedbackReview;
use Illuminate\Support\Str;

/**
 * Builds every Talent Management section of the member profile from the talent
 * tables. Pure read aggregation — controllers eager-load the relations and gate
 * the performance-sensitive sections by policy.
 */
class TalentProfile
{
    /** Relations the talent sections read, for eager-loading. */
    public const EAGER = [
        'skillEntries',
        'certifications',
        'kpis',
        'learningItems',
        'reviewsReceived',
        'successionPlan',
    ];

    /**
     * Assemble every talent prop for the profile page, applying the two gates:
     *  - $performance: KPI, 360°, Talent Score (managers + self)
     *  - $succession:  succession plan (managers only)
     *
     * Capability sections (skill gap, career roadmap, certifications, learning)
     * are open to anyone who may view the profile.
     *
     * @return array<string, mixed>
     */
    public static function bundle(Employee $employee, bool $performance, bool $succession): array
    {
        return [
            'talentScore' => $performance ? self::talentScore($employee) : null,
            'skillGap' => self::skillGap($employee),
            'careerRoadmap' => self::careerRoadmap($employee),
            'certifications' => self::certifications($employee),
            'learning' => self::learning($employee),
            'kpis' => $performance ? self::kpis($employee) : null,
            'feedback360' => $performance ? self::feedback360($employee) : null,
            'succession' => $succession ? self::succession($employee) : null,
        ];
    }

    /**
     * Skill matrix — prefers the leveled `employee_skills` rows, falling back to
     * the quick `skills` JSON list so older records still render.
     *
     * @return array{groups: list<array<string, mixed>>, total:int, has_levels:bool}
     */
    public static function skillMatrix(Employee $employee): array
    {
        $entries = $employee->relationLoaded('skillEntries') ? $employee->skillEntries : $employee->skillEntries()->get();

        if ($entries->isNotEmpty()) {
            $details = $entries->map(fn ($s) => [
                'name' => $s->name,
                'category' => $s->category,
                'level' => $s->level,
                'years' => $s->years_experience !== null ? (float) $s->years_experience : null,
                'certified' => (bool) $s->is_certified,
            ])->all();

            return SkillCatalog::build($entries->pluck('name')->all(), $details);
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];

        return SkillCatalog::build($employee->skills, $meta['skill_details'] ?? null);
    }

    /**
     * @return array{total:int|null, components: list<array<string, mixed>>}
     */
    public static function talentScore(Employee $employee): array
    {
        return TalentScore::for($employee);
    }

    /**
     * Skill-gap vs the next career level's requirements.
     *
     * @return array{target: array{key:string, name:string}, met:bool, items: list<array<string, mixed>>}|null
     */
    public static function skillGap(Employee $employee): ?array
    {
        $currentRank = Seniority::for($employee)['rank'];
        $target = CareerLevel::query()->where('rank', '>', $currentRank)->orderBy('rank')->first();

        if ($target === null || $target->requiredSkills() === []) {
            return null;
        }

        $current = self::currentSkillLevels($employee);

        $items = [];
        foreach ($target->requiredSkills() as $skill => $required) {
            $have = $current[Str::lower($skill)] ?? 0;
            $items[] = [
                'name' => $skill,
                'required' => (int) $required,
                'current' => $have,
                'gap' => max(0, (int) $required - $have),
                'required_pct' => (int) round((int) $required / 5 * 100),
                'current_pct' => (int) round($have / 5 * 100),
            ];
        }

        return [
            'target' => ['key' => $target->key, 'name' => $target->name],
            'met' => collect($items)->every(fn ($i) => $i['gap'] === 0),
            'items' => $items,
        ];
    }

    /**
     * The full career ladder, annotated with this person's position.
     *
     * @return list<array<string, mixed>>
     */
    public static function careerRoadmap(Employee $employee): array
    {
        $currentRank = Seniority::for($employee)['rank'];

        return CareerLevel::query()->orderBy('rank')->get()->map(function ($lvl) use ($currentRank) {
            $req = is_array($lvl->requirements) ? $lvl->requirements : [];

            return [
                'key' => $lvl->key,
                'name' => $lvl->name,
                'rank' => $lvl->rank,
                'description' => $lvl->description,
                'requirements' => [
                    'skills' => $lvl->requiredSkills(),
                    'kpi' => $req['kpi'] ?? null,
                    'certifications' => $req['certifications'] ?? null,
                ],
                'achieved' => $lvl->rank <= $currentRank,
                'is_current' => $lvl->rank === $currentRank,
                'is_target' => $lvl->rank === $currentRank + 1,
            ];
        })->values()->all();
    }

    /**
     * KPIs grouped by period type, keeping the most recent period of each.
     *
     * @return list<array<string, mixed>>
     */
    public static function kpis(Employee $employee): array
    {
        $kpis = $employee->relationLoaded('kpis') ? $employee->kpis : $employee->kpis()->get();
        if ($kpis->isEmpty()) {
            return [];
        }

        return $kpis->groupBy(fn ($k) => $k->period_type->value)
            ->map(function ($group) {
                $latestPeriod = $group->max('period');
                $items = $group->where('period', $latestPeriod)->values();
                $type = $items->first()->period_type;

                $attainments = $items->map(fn ($k) => $k->attainment())->filter(fn ($a) => $a !== null);
                $score = $attainments->isNotEmpty() ? (int) round(min($attainments->avg(), 1.0) * 100) : null;

                return [
                    'period_type' => self::enum($type),
                    'period' => $latestPeriod,
                    'score' => $score,
                    'items' => $items->map(fn ($k) => [
                        'name' => $k->name,
                        'target' => $k->target !== null ? (float) $k->target : null,
                        'actual' => $k->actual !== null ? (float) $k->actual : null,
                        'unit' => $k->unit,
                        'attainment_pct' => $k->attainment() !== null ? (int) round($k->attainment() * 100) : null,
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function certifications(Employee $employee): array
    {
        $certs = $employee->relationLoaded('certifications') ? $employee->certifications : $employee->certifications()->get();

        return $certs->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'provider' => $c->provider,
            'credential_id' => $c->credential_id,
            'credential_url' => $c->credential_url,
            'issued_at' => $c->issued_at?->toDateString(),
            'expires_at' => $c->expires_at?->toDateString(),
            'status' => self::enum($c->status()),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function learning(Employee $employee): array
    {
        $items = $employee->relationLoaded('learningItems') ? $employee->learningItems : $employee->learningItems()->get();

        return $items->map(fn ($l) => [
            'id' => $l->id,
            'title' => $l->title,
            'provider' => $l->provider,
            'category' => $l->category,
            'status' => self::enum($l->status),
            'progress' => $l->progress,
            'url' => $l->url,
            'completed_at' => $l->completed_at?->toDateString(),
        ])->values()->all();
    }

    /**
     * 360° feedback grouped by reviewer type, plus an overall per-dimension average.
     *
     * @return array{dimensions: list<array<string, mixed>>, groups: list<array<string, mixed>>, total:int}|null
     */
    public static function feedback360(Employee $employee): ?array
    {
        $reviews = $employee->relationLoaded('reviewsReceived') ? $employee->reviewsReceived : $employee->reviewsReceived()->get();
        if ($reviews->isEmpty()) {
            return null;
        }

        $dimensions = [];
        foreach (FeedbackReview::DIMENSIONS as $col => $label) {
            $vals = $reviews->pluck($col)->filter(fn ($v) => $v !== null && $v > 0);
            $dimensions[] = [
                'key' => $col,
                'label' => $label,
                'avg' => $vals->isNotEmpty() ? round($vals->avg(), 1) : null,
            ];
        }

        $groups = $reviews->groupBy(fn ($r) => $r->review_type->value)->map(function ($group) {
            $type = $group->first()->review_type;
            $ratings = [];
            foreach (FeedbackReview::DIMENSIONS as $col => $label) {
                $vals = $group->pluck($col)->filter(fn ($v) => $v !== null && $v > 0);
                $ratings[] = ['key' => $col, 'label' => $label, 'value' => $vals->isNotEmpty() ? round($vals->avg(), 1) : null];
            }

            return [
                'type' => self::enum($type),
                'count' => $group->count(),
                'ratings' => $ratings,
                'comments' => $group->pluck('comment')->filter()->values()->all(),
            ];
        })->values()->all();

        return ['dimensions' => $dimensions, 'groups' => $groups, 'total' => $reviews->count()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function succession(Employee $employee): ?array
    {
        $plan = $employee->relationLoaded('successionPlan') ? $employee->successionPlan : $employee->successionPlan()->first();
        if ($plan === null) {
            return null;
        }

        return [
            'readiness' => self::enum($plan->readiness),
            'risk_score' => $plan->risk_score,
            'retention_score' => $plan->retention_score,
            'promotion_score' => $plan->promotion_score,
            'target_role' => $plan->target_role,
            'note' => $plan->note,
        ];
    }

    /**
     * Lowercased current skill levels keyed by skill name.
     *
     * @return array<string, int>
     */
    private static function currentSkillLevels(Employee $employee): array
    {
        $entries = $employee->relationLoaded('skillEntries') ? $employee->skillEntries : $employee->skillEntries()->get();
        $out = [];
        foreach ($entries as $s) {
            $out[Str::lower($s->name)] = (int) $s->level;
        }

        return $out;
    }

    /**
     * @return array{value:string, label:string, color:string|null}|null
     */
    private static function enum(?\BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return [
            'value' => $enum->value,
            'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value,
            'color' => method_exists($enum, 'color') ? $enum->color() : null,
        ];
    }
}
