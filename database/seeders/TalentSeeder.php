<?php

namespace Database\Seeders;

use App\Models\CareerLevel;
use App\Models\Employee;
use App\Support\Profile\Seniority;
use App\Support\Profile\SkillCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for the Talent module: the global career ladder plus a believable
 * skill matrix, certifications, KPIs, learning, 360° feedback and succession
 * plan for each employee. Idempotent — safe to re-run.
 */
class TalentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCareerLevels();

        Employee::query()->get()->each(fn (Employee $e) => $this->seedForEmployee($e));
    }

    private function seedCareerLevels(): void
    {
        $levels = [
            ['key' => 'intern', 'name' => 'Thực tập', 'rank' => 0, 'requirements' => ['skills' => []]],
            ['key' => 'junior', 'name' => 'Junior', 'rank' => 1, 'requirements' => ['skills' => ['Laravel' => 2, 'Vue.js' => 2], 'kpi' => 60]],
            ['key' => 'middle', 'name' => 'Middle', 'rank' => 2, 'requirements' => ['skills' => ['Laravel' => 3, 'Vue.js' => 3, 'MySQL' => 3], 'kpi' => 70]],
            ['key' => 'senior', 'name' => 'Senior', 'rank' => 3, 'requirements' => ['skills' => ['Laravel' => 4, 'Docker' => 3, 'CI/CD' => 3, 'AWS' => 2], 'kpi' => 80, 'certifications' => 1]],
            ['key' => 'lead', 'name' => 'Lead', 'rank' => 4, 'requirements' => ['skills' => ['Laravel' => 4, 'Leadership' => 4, 'Project Management' => 3], 'kpi' => 85, 'certifications' => 2]],
            ['key' => 'manager', 'name' => 'Manager', 'rank' => 5, 'requirements' => ['skills' => ['Leadership' => 5, 'Project Management' => 4], 'kpi' => 90, 'certifications' => 2]],
        ];

        foreach ($levels as $i => $lvl) {
            CareerLevel::updateOrCreate(
                ['key' => $lvl['key']],
                [
                    'name' => $lvl['name'],
                    'rank' => $lvl['rank'],
                    'description' => "Cấp độ {$lvl['name']} trong lộ trình kỹ sư.",
                    'requirements' => $lvl['requirements'],
                    'sort_order' => $i,
                ],
            );
        }
    }

    private function seedForEmployee(Employee $e): void
    {
        $rank = max(1, Seniority::for($e)['rank']);

        $this->seedSkills($e, $rank);
        $this->seedCertifications($e, $rank);
        $this->seedKpis($e, $rank);
        $this->seedLearning($e);
        $this->seedReviews($e, $rank);
        $this->seedSuccession($e, $rank);
    }

    private function seedSkills(Employee $e, int $rank): void
    {
        if ($e->skillEntries()->exists()) {
            return;
        }

        $base = is_array($e->skills) && $e->skills !== []
            ? $e->skills
            : ['php', 'laravel', 'vue', 'mysql'];

        // Add a few breadth skills so the matrix spans several domains.
        $names = array_values(array_unique(array_merge($base, [
            'Docker', 'CI/CD', 'Redis', 'OpenAI', 'Scrum',
        ])));

        $order = 0;
        foreach ($names as $i => $name) {
            // Level glued to seniority, with light variation per skill.
            $level = max(1, min(5, $rank + (($i % 3) - 1)));
            $e->skillEntries()->create([
                'name' => SkillCatalog::displayName($name),
                'category' => SkillCatalog::categoryFor($name),
                'level' => $level,
                'years_experience' => round($level * 0.9, 1),
                'is_certified' => $level >= 4 && $i % 2 === 0,
                'sort_order' => $order++,
            ]);
        }
    }

    private function seedCertifications(Employee $e, int $rank): void
    {
        if ($rank < 2 || $e->certifications()->exists()) {
            return;
        }

        $catalog = [
            ['name' => 'AWS Certified Developer', 'provider' => 'Amazon Web Services'],
            ['name' => 'Professional Scrum Master I', 'provider' => 'Scrum.org'],
            ['name' => 'Google Cloud Associate Engineer', 'provider' => 'Google Cloud'],
        ];

        foreach (array_slice($catalog, 0, min($rank, 3)) as $i => $c) {
            $e->certifications()->create([
                'name' => $c['name'],
                'provider' => $c['provider'],
                'issued_at' => Carbon::now()->subMonths(6 + $i * 5),
                'expires_at' => Carbon::now()->addMonths(($i % 3) === 0 ? 1 : 18),
            ]);
        }
    }

    private function seedKpis(Employee $e, int $rank): void
    {
        if ($e->kpis()->exists()) {
            return;
        }

        $now = Carbon::now();
        $base = 60 + $rank * 6; // higher seniority → stronger attainment
        $periods = [
            ['type' => 'month', 'period' => $now->format('Y-m')],
            ['type' => 'quarter', 'period' => $now->format('Y').'-Q'.$now->quarter],
            ['type' => 'year', 'period' => $now->format('Y')],
        ];
        $metrics = [
            ['name' => 'Hoàn thành công việc đúng hạn', 'target' => 100, 'unit' => '%'],
            ['name' => 'Chất lượng code (review pass)', 'target' => 95, 'unit' => '%'],
            ['name' => 'Story points hoàn thành', 'target' => 40, 'unit' => 'SP'],
        ];

        foreach ($periods as $p) {
            foreach ($metrics as $i => $m) {
                $ratio = min(1.15, ($base + $i * 4) / 100);
                $e->kpis()->create([
                    'period_type' => $p['type'],
                    'period' => $p['period'],
                    'name' => $m['name'],
                    'target' => $m['target'],
                    'actual' => round($m['target'] * $ratio, 1),
                    'unit' => $m['unit'],
                    'weight' => 3 - $i,
                ]);
            }
        }
    }

    private function seedLearning(Employee $e): void
    {
        if ($e->learningItems()->exists()) {
            return;
        }

        $items = [
            ['title' => 'Laravel nâng cao & kiến trúc', 'provider' => 'Laracasts', 'status' => 'completed', 'progress' => 100, 'category' => 'backend'],
            ['title' => 'Thiết kế hệ thống quy mô lớn', 'provider' => 'Educative', 'status' => 'in_progress', 'progress' => 55, 'category' => 'devops'],
            ['title' => 'AI Engineering với RAG & Agent', 'provider' => 'DeepLearning.AI', 'status' => 'recommended', 'progress' => 0, 'category' => 'ai'],
            ['title' => 'Kỹ năng lãnh đạo kỹ thuật', 'provider' => 'Coursera', 'status' => 'planned', 'progress' => 0, 'category' => 'management'],
        ];

        foreach ($items as $it) {
            $e->learningItems()->create([
                'title' => $it['title'],
                'provider' => $it['provider'],
                'category' => $it['category'],
                'status' => $it['status'],
                'progress' => $it['progress'],
                'completed_at' => $it['status'] === 'completed' ? Carbon::now()->subMonths(2) : null,
                'started_at' => in_array($it['status'], ['completed', 'in_progress'], true) ? Carbon::now()->subMonths(3) : null,
            ]);
        }
    }

    private function seedReviews(Employee $e, int $rank): void
    {
        if ($e->reviewsReceived()->exists()) {
            return;
        }

        $b = min(5, 2 + intdiv($rank + 1, 2)); // baseline rating from seniority
        $period = Carbon::now()->format('Y').'-Q'.Carbon::now()->quarter;

        $reviews = [
            ['type' => 'self', 'd' => 0, 'comment' => 'Tự nhận thấy cần cải thiện kỹ năng giao tiếp đa nhóm.'],
            ['type' => 'manager', 'd' => 0, 'comment' => 'Chủ động, chất lượng kỹ thuật tốt, cần đẩy mạnh mentoring.'],
            ['type' => 'peer', 'd' => -1, 'comment' => 'Hỗ trợ đồng đội nhiệt tình, dễ phối hợp.'],
        ];

        foreach ($reviews as $r) {
            $e->reviewsReceived()->create([
                'review_type' => $r['type'],
                'period' => $period,
                'rating_technical' => $this->clampRating($b + 1 + $r['d']),
                'rating_communication' => $this->clampRating($b + $r['d']),
                'rating_ownership' => $this->clampRating($b + 1 + $r['d']),
                'rating_leadership' => $this->clampRating($b - 1 + $r['d']),
                'rating_teamwork' => $this->clampRating($b + $r['d']),
                'comment' => $r['comment'],
            ]);
        }
    }

    private function seedSuccession(Employee $e, int $rank): void
    {
        if ($e->successionPlan()->exists()) {
            return;
        }

        $readiness = match (true) {
            $rank >= 4 => 'ready',
            $rank >= 2 => 'potential',
            default => 'not_ready',
        };

        $e->successionPlan()->create([
            'readiness' => $readiness,
            'risk_score' => max(10, 60 - $rank * 8),
            'retention_score' => min(95, 55 + $rank * 7),
            'promotion_score' => min(95, 45 + $rank * 9),
            'target_role' => $rank >= 4 ? 'Engineering Manager' : 'Senior Engineer',
            'note' => 'Đánh giá kế nhiệm tự động (dữ liệu mẫu).',
        ]);
    }

    private function clampRating(int $v): int
    {
        return max(1, min(5, $v));
    }
}
