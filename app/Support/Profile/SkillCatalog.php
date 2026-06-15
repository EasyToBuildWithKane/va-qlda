<?php

namespace App\Support\Profile;

use Illuminate\Support\Str;

/**
 * Turns an employee's raw `skills` array (plain strings such as ["php","vue"])
 * — optionally enriched by `meta['skill_details']` — into a grouped, presentable
 * skill matrix for the profile page.
 *
 * Enriched format (optional, stored in employees.meta['skill_details']):
 *   [{ "name": "Laravel", "level": 5, "years": 4, "category": "backend",
 *      "certified": true, "projects": 6 }, ...]
 *
 * When no level data exists the matrix still groups skills by inferred domain so
 * the UI can render clean chips instead of fabricated progress bars.
 */
class SkillCatalog
{
    /**
     * Ordered category definitions: key => [label, keyword fragments].
     *
     * @var array<string, array{label:string, match:list<string>}>
     */
    private const CATEGORIES = [
        'backend' => ['label' => 'Backend', 'match' => [
            'php', 'laravel', 'symfony', 'node', 'express', 'nest', 'java', 'spring',
            'python', 'django', 'flask', 'fastapi', 'go', 'golang', 'rust', 'ruby',
            'rails', 'dotnet', '.net', 'c#', 'graphql', 'api', 'grpc',
        ]],
        'frontend' => ['label' => 'Frontend', 'match' => [
            'vue', 'react', 'next', 'nuxt', 'angular', 'svelte', 'tailwind',
            'javascript', 'typescript', 'js', 'ts', 'html', 'css', 'sass', 'inertia',
            'alpine', 'bootstrap', 'vite', 'webpack',
        ]],
        'mobile' => ['label' => 'Mobile', 'match' => [
            'flutter', 'react native', 'swift', 'kotlin', 'android', 'ios', 'dart',
        ]],
        'data' => ['label' => 'Dữ liệu & CSDL', 'match' => [
            'mysql', 'postgres', 'postgresql', 'sql', 'mongo', 'mongodb', 'redis',
            'elasticsearch', 'sqlite', 'mariadb', 'bigquery', 'snowflake', 'etl',
            'pandas', 'spark', 'kafka', 'data',
        ]],
        'ai' => ['label' => 'AI Engineering', 'match' => [
            'openai', 'claude', 'anthropic', 'gemini', 'llm', 'mcp', 'rag', 'agent',
            'prompt', 'langchain', 'huggingface', 'tensorflow', 'pytorch', 'ml',
            'machine learning', 'n8n', 'make', 'zapier', 'automation', 'embedding',
        ]],
        'devops' => ['label' => 'DevOps & Hạ tầng', 'match' => [
            'docker', 'kubernetes', 'k8s', 'aws', 'gcp', 'azure', 'terraform',
            'ansible', 'ci/cd', 'cicd', 'jenkins', 'github actions', 'gitlab',
            'nginx', 'linux', 'devops',
        ]],
        'design' => ['label' => 'Thiết kế & UX', 'match' => [
            'figma', 'sketch', 'photoshop', 'illustrator', 'ui', 'ux', 'design',
            'prototyping', 'wireframe',
        ]],
        'management' => ['label' => 'Quản lý & Quy trình', 'match' => [
            'scrum', 'agile', 'kanban', 'leadership', 'mentoring', 'project management',
            'product', 'jira', 'planning', 'quản lý', 'lãnh đạo',
        ]],
    ];

    /** Canonical display names for common skills (keyed by lowercase). */
    private const DISPLAY_NAMES = [
        'php' => 'PHP', 'laravel' => 'Laravel', 'mysql' => 'MySQL', 'redis' => 'Redis',
        'docker' => 'Docker', 'vue' => 'Vue.js', 'vuejs' => 'Vue.js', 'react' => 'React',
        'nextjs' => 'Next.js', 'next' => 'Next.js', 'tailwind' => 'Tailwind CSS',
        'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'js' => 'JavaScript',
        'ts' => 'TypeScript', 'nodejs' => 'Node.js', 'node' => 'Node.js', 'aws' => 'AWS',
        'gcp' => 'Google Cloud', 'ci/cd' => 'CI/CD', 'cicd' => 'CI/CD', 'openai' => 'OpenAI',
        'mcp' => 'MCP', 'rag' => 'RAG', 'n8n' => 'n8n', 'api' => 'API', 'ux' => 'UX',
        'ui' => 'UI', 'inertia' => 'Inertia', 'postgresql' => 'PostgreSQL',
    ];

    /**
     * @param  array<int, string>|null  $skills  Raw employees.skills array.
     * @param  array<int, array<string, mixed>>|null  $details  Optional meta['skill_details'].
     * @return array{groups: list<array{key:string, label:string, items: list<array<string, mixed>>}>, total:int, has_levels:bool}
     */
    public static function build(?array $skills, ?array $details = null): array
    {
        if ($skills !== null && ! is_array($skills)) {
            $skills = [];
        }
        /** @var array<string, array<string, mixed>> $byKey indexed by normalized name */
        $byKey = [];
        $hasLevels = false;

        foreach ($skills ?? [] as $raw) {
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            $key = Str::lower(trim($raw));
            $byKey[$key] = [
                'name' => self::displayName($raw),
                'category' => self::categorize($key),
                'level' => null,
                'percent' => null,
                'years' => null,
                'certified' => false,
                'projects' => null,
                'note' => null,
            ];
        }

        foreach ($details ?? [] as $detail) {
            $name = is_array($detail) ? ($detail['name'] ?? null) : null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $key = Str::lower(trim($name));
            $level = isset($detail['level']) ? (int) $detail['level'] : null;
            if ($level !== null) {
                $hasLevels = true;
            }
            $byKey[$key] = [
                'name' => self::displayName($name),
                'category' => $detail['category'] ?? self::categorize($key),
                'level' => $level,
                'percent' => $level !== null ? (int) round($level / 5 * 100) : null,
                'years' => isset($detail['years']) ? (float) $detail['years'] : null,
                'certified' => (bool) ($detail['certified'] ?? false),
                'projects' => isset($detail['projects']) ? (int) $detail['projects'] : null,
                'note' => isset($detail['note']) && trim((string) $detail['note']) !== '' ? (string) $detail['note'] : null,
            ];
        }

        // Bucket into ordered groups; collect "other" last.
        $groups = [];
        foreach (array_keys(self::CATEGORIES) as $catKey) {
            $items = array_values(array_filter($byKey, fn ($s) => $s['category'] === $catKey));
            if ($items !== []) {
                $groups[] = ['key' => $catKey, 'label' => self::CATEGORIES[$catKey]['label'], 'items' => $items];
            }
        }
        $other = array_values(array_filter($byKey, fn ($s) => ! isset(self::CATEGORIES[$s['category']])));
        if ($other !== []) {
            $groups[] = ['key' => 'other', 'label' => 'Khác', 'items' => $other];
        }

        return [
            'groups' => $groups,
            'total' => count($byKey),
            'has_levels' => $hasLevels,
        ];
    }

    /** Infer the category bucket for a raw skill name (public helper). */
    public static function categoryFor(string $name): string
    {
        return self::categorize(Str::lower(trim($name)));
    }

    /**
     * Ordered competency domains (key => label), for radar / gap analysis.
     *
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $key => $def) {
            $out[$key] = $def['label'];
        }

        return $out;
    }

    private static function categorize(string $lowerName): string
    {
        foreach (self::CATEGORIES as $key => $def) {
            foreach ($def['match'] as $needle) {
                if (str_contains($lowerName, $needle)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    /** Canonical display name for a raw skill string (public helper). */
    public static function displayName(string $raw): string
    {
        $key = Str::lower(trim($raw));

        return self::DISPLAY_NAMES[$key] ?? Str::title(trim($raw));
    }
}
