<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Support\Enums\KbArticleStatus;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $author = Employee::query()->where('email', 'admin@vaschools.edu.vn')->first()
            ?? Employee::query()->first();

        if (! $author) {
            $this->command?->warn('KnowledgeBaseSeeder: không có employee — bỏ qua.');

            return;
        }

        $general = KbCategory::query()->where('slug', 'general')->first();
        $dev = KbCategory::query()->where('slug', 'development')->first();
        $pm = KbCategory::query()->where('slug', 'project-management')->first();

        if (! $general || ! $dev || ! $pm) {
            $this->command?->warn('KnowledgeBaseSeeder: thiếu kb_categories — chạy migration KB trước.');

            return;
        }

        $tags = [
            ['name' => 'Coaching', 'slug' => 'coaching'],
            ['name' => 'Onboarding', 'slug' => 'onboarding'],
            ['name' => 'Laravel', 'slug' => 'laravel'],
        ];

        foreach ($tags as $row) {
            KbTag::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name']],
            );
        }

        $articles = [
            [
                'slug' => 'coaching-1',
                'category_id' => $general->id,
                'title' => 'Coaching 1',
                'excerpt' => '<p>Giới thiệu ngắn về buổi coaching đầu tiên trong chuỗi mentoring nội bộ VA.</p>',
                'content' => <<<'HTML'
<h2 id="muc-1-gioi-thieu">Giới thiệu</h2>
<p>Coaching là hình thức đồng hành giúp nhân sự phát triển kỹ năng qua phản hồi có cấu trúc và mục tiêu rõ ràng.</p>
<h2 id="muc-2-chuan-bi">Chuẩn bị buổi coaching</h2>
<p>Trước buổi họp, mentor và mentee nên thống nhất agenda, tài liệu tham chiếu và kết quả mong muốn.</p>
<ul>
<li>Chọn không gian yên tĩnh hoặc link họp ổn định</li>
<li>Ghi chú 2–3 điểm cần thảo luận</li>
<li>Chuẩn bị ví dụ thực tế từ sprint gần nhất</li>
</ul>
<h2 id="muc-3-sau-buoi">Sau buổi coaching</h2>
<p>Ghi lại action items, deadline và người phụ trách. Theo dõi tiến độ trong buổi coaching tiếp theo.</p>
HTML,
                'view_count' => 42,
                'tag_slugs' => ['coaching', 'onboarding'],
            ],
            [
                'slug' => 'quy-trinh-code-review',
                'category_id' => $dev->id,
                'title' => 'Quy trình code review trong dự án',
                'excerpt' => '<p>Các bước review PR, checklist chất lượng và cách phản hồi mang tính xây dựng.</p>',
                'content' => '<h2 id="muc-checklist">Checklist</h2><p>Kiểm tra test, Pint, và phạm vi thay đổi trước khi merge.</p><h2 id="muc-feedback">Phản hồi</h2><p>Mô tả vấn đề, đề xuất giải pháp, tách nitpick khỏi blocking comment.</p>',
                'view_count' => 128,
                'tag_slugs' => ['laravel'],
            ],
            [
                'slug' => 'kickoff-du-an-mau',
                'category_id' => $pm->id,
                'title' => 'Kickoff dự án — mẫu agenda',
                'excerpt' => '<p>Agenda kickoff 60 phút: mục tiêu, phạm vi, rủi ro và communication plan.</p>',
                'content' => '<h2 id="muc-agenda">Agenda 60 phút</h2><p>10 phút giới thiệu · 20 phút scope · 15 phút timeline · 15 phút Q&amp;A.</p>',
                'view_count' => 67,
                'tag_slugs' => ['onboarding'],
            ],
        ];

        $publishedAt = now()->subDays(3);

        foreach ($articles as $row) {
            $article = KbArticle::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $row['category_id'],
                    'author_id' => $author->id,
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'content' => $row['content'],
                    'status' => KbArticleStatus::Published,
                    'view_count' => $row['view_count'],
                    'published_at' => $publishedAt,
                    'archived_at' => null,
                ],
            );

            $tagIds = KbTag::query()
                ->whereIn('slug', $row['tag_slugs'])
                ->pluck('id');

            $article->tags()->sync($tagIds);
        }

        $this->command?->info('KnowledgeBaseSeeder: '.count($articles).' bài viết (vd. /knowledge-base/articles/coaching-1).');
    }
}
