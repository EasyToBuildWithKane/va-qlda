<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Support\Enums\KbArticleStatus;
use App\Support\KnowledgeBase\KbMarkdownHtml;
use App\Support\KnowledgeBase\KbTagSync;
use FilesystemIterator;
use Illuminate\Database\Seeder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class KnowledgeBaseSeeder extends Seeder
{
    private const AUTHOR_EMAIL = 'khoana@hcm.vaschools.edu.vn';

    private const AUTHOR_NAME = 'Nguyễn Anh Khoa';

    private const AUTHOR_CODE = 'EMP-KHOANA';

    /** @var array<string, string> */
    private const CATEGORY_BY_PATH = [
        'docs/KNOWLEDGE_BASE' => 'general',
        'docs/COACHING' => 'general',
        'docs/PROJECT_OVERVIEW' => 'general',
        'docs/NEXT_STEPS' => 'general',
        'docs/TECHNICAL_DEBT' => 'general',
        'docs/REFACTOR_PLAN' => 'general',
        'docs/AI_ACCOUNTS' => 'ai-automation',
        'docs/DAILY_REPORT' => 'project-management',
        'docs/IMPORT_EXPORT' => 'development',
        'docs/FRONTEND' => 'development',
        'docs/ARCHITECTURE' => 'development',
        'docs/FOLDER_STRUCTURE' => 'development',
        'docs/API_STRUCTURE' => 'development',
        'docs/DATABASE' => 'development',
        'docs/CONGNGHE' => 'development',
        'docs/FLOWS' => 'development',
        'docs/SYSTEM_CONFIG' => 'internal-docs',
    ];

    public function run(): void
    {
        $author = $this->resolveAuthor();

        $categories = KbCategory::query()->pluck('id', 'slug');
        if ($categories->isEmpty()) {
            $this->command?->warn('KnowledgeBaseSeeder: thiếu kb_categories — chạy migration KB trước.');

            return;
        }

        $this->ensureTags();

        $files = $this->collectMarkdownFiles();
        if ($files === []) {
            $this->command?->warn('KnowledgeBaseSeeder: không tìm thấy file .md trong docs/ hoặc _dev/.');

            return;
        }

        $publishedAt = now()->subDay();
        $count = 0;

        foreach ($files as $relativePath) {
            $absolute = base_path($relativePath);
            if (! is_readable($absolute)) {
                continue;
            }

            $markdown = file_get_contents($absolute);
            if ($markdown === false || trim($markdown) === '') {
                continue;
            }

            $basename = basename($relativePath);
            $title = KbMarkdownHtml::titleFromMarkdown($markdown, $basename);
            $slug = KbMarkdownHtml::slugForRepoPath($relativePath);
            $categorySlug = $this->categorySlugForPath($relativePath);
            $categoryId = $categories[$categorySlug] ?? $categories['internal-docs'] ?? $categories->first();

            $bodyHtml = KbMarkdownHtml::toHtml($markdown);
            $sourceNote = '<p class="text-sm text-slate-500"><em>Nguồn repository: '
                .e($relativePath).'</em></p>';
            $content = $sourceNote.$bodyHtml;

            $article = KbArticle::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categoryId,
                    'author_id' => $author->id,
                    'title' => $title,
                    'excerpt' => KbMarkdownHtml::excerptHtml($markdown),
                    'content' => $content,
                    'status' => KbArticleStatus::Published,
                    'view_count' => 0,
                    'published_at' => $publishedAt,
                    'archived_at' => null,
                ],
            );

            KbTagSync::sync($article, $this->tagsForPath($relativePath));
            $count++;
        }

        $this->command?->info("KnowledgeBaseSeeder: {$count} bài từ docs/ + _dev/ (tác giả: {$author->full_name}).");
    }

    private function resolveAuthor(): Employee
    {
        return Employee::query()->updateOrCreate(
            ['email' => self::AUTHOR_EMAIL],
            [
                'code' => self::AUTHOR_CODE,
                'full_name' => self::AUTHOR_NAME,
                'role_title' => 'Tác giả tài liệu',
                'join_date' => now()->subYears(2),
                'skills' => ['documentation', 'laravel', 'vue'],
                'is_active' => true,
            ],
        );
    }

    private function ensureTags(): void
    {
        $tags = [
            ['name' => 'Tài liệu kỹ thuật', 'slug' => 'tai-lieu-ky-thuat'],
            ['name' => 'Dev ops', 'slug' => 'dev-ops'],
            ['name' => 'Onboarding', 'slug' => 'onboarding'],
            ['name' => 'Tiếng Việt', 'slug' => 'tieng-viet'],
            ['name' => 'VA-QLDA', 'slug' => 'va-qlda'],
        ];

        foreach ($tags as $row) {
            KbTag::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name']],
            );
        }
    }

    /**
     * @return list<string> paths relative to project root, forward slashes
     */
    private function collectMarkdownFiles(): array
    {
        $paths = [];

        foreach (['docs', '_dev'] as $root) {
            $base = base_path($root);
            if (! is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $full = $file->getPathname();
                $relative = str_replace('\\', '/', substr($full, strlen(base_path()) + 1));
                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    private function categorySlugForPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach (self::CATEGORY_BY_PATH as $needle => $slug) {
            if (str_contains($normalized, $needle)) {
                return $slug;
            }
        }

        if (str_starts_with($normalized, '_dev/vi/')) {
            return 'internal-docs';
        }

        if (str_starts_with($normalized, '_dev/')) {
            return 'internal-docs';
        }

        if (str_starts_with($normalized, 'docs/')) {
            return 'development';
        }

        return 'internal-docs';
    }

    /**
     * @return list<string>
     */
    private function tagsForPath(string $relativePath): array
    {
        $tags = ['VA-QLDA', 'Tài liệu kỹ thuật'];

        if (str_starts_with($relativePath, '_dev/vi/')) {
            $tags[] = 'Tiếng Việt';
            $tags[] = 'Dev ops';
        } elseif (str_starts_with($relativePath, '_dev/')) {
            $tags[] = 'Dev ops';
            $tags[] = 'Onboarding';
        } else {
            $tags[] = 'Onboarding';
        }

        return array_values(array_unique($tags));
    }
}
