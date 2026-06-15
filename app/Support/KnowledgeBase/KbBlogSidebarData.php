<?php

namespace App\Support\KnowledgeBase;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Models\SystemAccount;
use App\Support\Enums\KbArticleStatus;
use Illuminate\Database\Eloquent\Builder;

class KbBlogSidebarData
{
    /**
     * @return array{
     *     categories: array<int, array<string, mixed>>,
     *     recentPosts: array<int, array<string, mixed>>,
     *     popularPosts: array<int, array<string, mixed>>,
     *     tags: array<int, array<string, mixed>>
     * }
     */
    public static function build(SystemAccount $account): array
    {
        return [
            'categories' => self::categories($account),
            'recentPosts' => self::compactPosts(
                self::publishedQuery($account)->latest('published_at')->latest('id'),
                8,
            ),
            'popularPosts' => self::compactPosts(
                self::publishedQuery($account)->orderByDesc('view_count')->orderByDesc('published_at'),
                5,
            ),
            'tags' => self::tags($account),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function categories(SystemAccount $account): array
    {
        return KbCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['articles as articles_count' => fn ($q) => self::publishedScope($q, $account)])
            ->get(['id', 'name', 'slug', 'color'])
            ->map(fn (KbCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'color' => $cat->color,
                'articles_count' => (int) $cat->articles_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function tags(SystemAccount $account): array
    {
        return KbTag::query()
            ->withCount(['articles as articles_count' => fn ($q) => self::publishedScope($q, $account)])
            ->orderByDesc('articles_count')
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'slug'])
            ->filter(fn (KbTag $tag) => (int) $tag->articles_count > 0)
            ->take(24)
            ->map(fn (KbTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'articles_count' => (int) $tag->articles_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function compactPosts(Builder $query, int $limit): array
    {
        return $query
            ->with(['galleryImages' => fn ($q) => $q->orderBy('sort_order')->limit(1)])
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'published_at', 'view_count'])
            ->map(fn (KbArticle $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toIso8601String(),
                'view_count' => $article->view_count,
                'cover_url' => $article->coverImageUrl(),
            ])
            ->values()
            ->all();
    }

    private static function publishedQuery(SystemAccount $account): Builder
    {
        $query = KbArticle::query()->with(['category']);

        return self::publishedScope($query, $account);
    }

    /**
     * @param  Builder<KbArticle>  $query
     * @return Builder<KbArticle>
     */
    private static function publishedScope(Builder $query, SystemAccount $account): Builder
    {
        if (! in_array($account->role->value, ['admin', 'lead'], true)) {
            return $query->where('status', KbArticleStatus::Published->value);
        }

        return $query->where('status', KbArticleStatus::Published->value);
    }
}
