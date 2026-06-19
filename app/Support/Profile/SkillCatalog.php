<?php

namespace App\Support\Profile;

use Illuminate\Support\Str;

/**
 * Turns an employee's raw `skills` array (plain strings such as ["php","vue"])
 * — optionally enriched by `meta['skill_details']` — into a grouped, presentable
 * skill matrix for the profile page.
 *
 * Enriched format (optional, stored in employees.meta['skill_details']):
 *   [{ "name": "Laravel", "level": 5, "years": 4, "category": "Backend",
 *      "note": "..." }, ...]
 *
 * `category` is a free-form group label (e.g. "Lập trình Web", "DevOps").
 * Legacy slug values (backend, frontend, …) still map to their Vietnamese labels.
 */
class SkillCatalog
{
    public const DEFAULT_GROUP = 'Chung';

    /**
     * Legacy slug → label (backward compatible reads).
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

        /**
         * Enriched details are authoritative and define both grouping and order.
         * The plain `skills` names array only fills in legacy skills that have no
         * matching detail. Items are deduped by (name + category) so the same
         * skill name may legitimately appear in two different groups.
         *
         * @var list<array<string, mixed>> $items
         */
        $items = [];
        $seen = [];        // "name|category" (lowercased) => true
        $detailNames = []; // lowercased name => true

        foreach ($details ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $name = self::detailName($detail);
            if ($name === '') {
                continue;
            }
            $nameLower = Str::lower($name);
            $detailNames[$nameLower] = true;

            $category = isset($detail['category']) && trim((string) $detail['category']) !== ''
                ? trim((string) $detail['category'])
                : self::DEFAULT_GROUP;
            $level = isset($detail['level']) && is_numeric($detail['level'])
                ? max(1, min(5, (int) $detail['level']))
                : 3;

            $dedup = $nameLower.'|'.Str::lower($category);
            if (isset($seen[$dedup])) {
                continue;
            }
            $seen[$dedup] = true;
            $items[] = self::filledSkill($name, $category, $level, $detail);
        }

        foreach ($skills ?? [] as $raw) {
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            $name = trim($raw);
            $nameLower = Str::lower($name);
            if (isset($detailNames[$nameLower])) {
                continue; // already represented by an enriched detail
            }
            $dedup = $nameLower.'|'.Str::lower(self::DEFAULT_GROUP);
            if (isset($seen[$dedup])) {
                continue;
            }
            $seen[$dedup] = true;
            $items[] = self::blankSkill($name, self::DEFAULT_GROUP);
        }

        foreach ($items as &$item) {
            self::applyLevelPercent($item);
        }
        unset($item);

        $groups = self::bucketIntoGroups($items);

        return [
            'groups' => $groups,
            'total' => count($items),
            'has_levels' => count($items) > 0,
        ];
    }

    /**
     * Ordered legacy competency domains (key => label).
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

    /**
     * @return array{key:string, label:string}
     */
    public static function resolveGroup(string $categoryRaw): array
    {
        $trimmed = trim($categoryRaw);
        if ($trimmed === '') {
            return ['key' => 'chung', 'label' => self::DEFAULT_GROUP];
        }

        $lower = Str::lower($trimmed);
        if (isset(self::CATEGORIES[$lower])) {
            return ['key' => $lower, 'label' => self::CATEGORIES[$lower]['label']];
        }

        $slug = Str::slug($trimmed);
        if ($slug === '') {
            $slug = 'group-'.substr(md5($trimmed), 0, 8);
        }

        return ['key' => $slug, 'label' => $trimmed];
    }

    /** Canonical display name for a raw skill string (public helper). */
    public static function displayName(string $raw): string
    {
        $key = Str::lower(trim($raw));

        return self::DISPLAY_NAMES[$key] ?? Str::title(trim($raw));
    }

    /**
     * @return array<string, mixed>
     */
    private static function blankSkill(string $raw, string $category): array
    {
        return [
            'name' => trim($raw),
            'category' => $category,
            'level' => 3,
            'percent' => null,
            'years' => null,
            'projects' => null,
            'note' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private static function filledSkill(string $name, string $category, int $level, array $detail): array
    {
        $trimmed = trim($name);

        return [
            'name' => $trimmed,
            'category' => $category,
            'level' => $level,
            'percent' => null,
            'years' => isset($detail['years']) && $detail['years'] !== '' && $detail['years'] !== null
                ? (float) $detail['years']
                : null,
            'projects' => isset($detail['projects']) ? (int) $detail['projects'] : null,
            'note' => isset($detail['note']) && trim((string) $detail['note']) !== ''
                ? trim((string) $detail['note'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function applyLevelPercent(array &$item): void
    {
        $level = isset($item['level']) && is_numeric($item['level'])
            ? max(1, min(5, (int) $item['level']))
            : 3;
        $item['level'] = $level;
        $item['percent'] = (int) round($level / 5 * 100);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{key:string, label:string, items: list<array<string, mixed>>}>
     */
    private static function bucketIntoGroups(array $items): array
    {
        /** @var array<string, array{key:string, label:string, items: list<array<string, mixed>>}> $map */
        $map = [];
        $order = [];

        foreach ($items as $item) {
            $resolved = self::resolveGroup((string) ($item['category'] ?? self::DEFAULT_GROUP));
            $gKey = $resolved['key'];
            if (! isset($map[$gKey])) {
                $map[$gKey] = ['key' => $gKey, 'label' => $resolved['label'], 'items' => []];
                $order[] = $gKey;
            }
            $map[$gKey]['items'][] = $item;
        }

        return array_values(array_map(fn (string $k) => $map[$k], $order));
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private static function detailName(array $detail): string
    {
        foreach (['name', 'title', 'label'] as $key) {
            if (! isset($detail[$key])) {
                continue;
            }
            $value = trim((string) $detail[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
