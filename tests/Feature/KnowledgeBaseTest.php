<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\SystemAccount;
use App\Support\Enums\KbArticleStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('knowledge-base.index'))->assertRedirect(route('tech.login'));
    }

    public function test_member_can_view_knowledge_base_index(): void
    {
        $this->actingAs($this->member())
            ->get(route('knowledge-base.index'))
            ->assertOk();
    }

    public function test_member_can_create_article(): void
    {
        $account = $this->member();
        $category = KbCategory::query()->where('slug', 'general')->first();
        $this->assertNotNull($category);

        $this->actingAs($account)
            ->post(route('knowledge-base.articles.store'), [
                'category_id' => $category->id,
                'title' => 'Bài test KB',
                'content' => '<p>Nội dung</p>',
                'status' => KbArticleStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kb_articles', [
            'title' => 'Bài test KB',
            'status' => KbArticleStatus::Published->value,
        ]);
    }

    public function test_viewer_cannot_create_article(): void
    {
        $viewer = SystemAccount::factory()->role(SystemRole::Viewer)->create();
        $category = KbCategory::query()->first();
        $this->assertNotNull($category);

        $this->actingAs($viewer)
            ->post(route('knowledge-base.articles.store'), [
                'category_id' => $category->id,
                'title' => 'Blocked',
                'status' => KbArticleStatus::Draft->value,
            ])
            ->assertForbidden();
    }

    public function test_published_article_show_increments_views(): void
    {
        $category = KbCategory::query()->first();
        $account = $this->member();
        $article = KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $account->employee_id,
            'title' => 'Show me',
            'slug' => 'show-me',
            'status' => KbArticleStatus::Published,
            'published_at' => now(),
            'content' => '<p>x</p>',
        ]);

        $this->actingAs($account)
            ->get(route('knowledge-base.articles.show', $article))
            ->assertOk();

        $this->assertSame(1, $article->fresh()->view_count);
    }

    public function test_member_can_upload_inline_image(): void
    {
        $account = $this->member();
        $category = KbCategory::query()->first();
        $article = KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $account->employee_id,
            'title' => 'Img',
            'slug' => 'img-test',
            'status' => KbArticleStatus::Draft,
            'content' => '',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('inline.jpg', 100, 100);

        $this->actingAs($account)
            ->post(route('knowledge-base.articles.images.store', $article), ['image' => $file])
            ->assertOk()
            ->assertJsonStructure(['url']);
    }

    public function test_index_search_finds_article_by_title(): void
    {
        $account = $this->member();
        $category = KbCategory::query()->first();
        KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $account->employee_id,
            'title' => 'UniqueAlphaKeyword',
            'slug' => 'unique-alpha',
            'status' => KbArticleStatus::Published,
            'published_at' => now(),
            'content' => '<p>body</p>',
        ]);

        $this->actingAs($account)
            ->get(route('knowledge-base.index', ['q' => 'UniqueAlphaKeyword']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('KnowledgeBase/Index')
                ->has('articles.data', 1));
    }

    public function test_export_data_returns_filtered_articles(): void
    {
        $account = $this->member();
        $category = KbCategory::query()->first();
        KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $account->employee_id,
            'title' => 'ExportMe',
            'slug' => 'export-me',
            'status' => KbArticleStatus::Published,
            'published_at' => now(),
            'content' => '',
        ]);

        $this->actingAs($account)
            ->getJson(route('knowledge-base.export-data', ['q' => 'ExportMe']))
            ->assertOk()
            ->assertJsonPath('articles.0.title', 'ExportMe');
    }

    public function test_gallery_upload_creates_gallery_usage_row(): void
    {
        $account = $this->member();
        $category = KbCategory::query()->first();
        $article = KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $account->employee_id,
            'title' => 'Gal',
            'slug' => 'gal',
            'status' => KbArticleStatus::Draft,
            'content' => '',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('gal.jpg', 80, 80);

        $this->actingAs($account)
            ->post(route('knowledge-base.articles.gallery.store', $article), [
                'image' => $file,
                'alt_text' => 'Mô tả',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kb_article_images', [
            'article_id' => $article->id,
            'usage' => 'gallery',
            'alt_text' => 'Mô tả',
        ]);
    }
}
