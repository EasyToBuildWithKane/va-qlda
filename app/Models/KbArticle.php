<?php

namespace App\Models;

use App\Support\Enums\KbArticleStatus;
use App\Support\Enums\KbImageUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property bool|null $is_favorite Runtime flag for show API
 * @property bool|null $is_read Runtime flag for show API
 */
class KbArticle extends Model
{
    protected $table = 'kb_articles';

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'view_count',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'status' => KbArticleStatus::class,
        'view_count' => 'integer',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (KbArticle $article) {
            if (! $article->slug) {
                $article->slug = static::uniqueSlug($article->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'bai-viet';
        }
        $slug = $base;
        $n = 1;
        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KbArticleStatus::Published->value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tags', 'article_id', 'tag_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(KbArticleImage::class, 'article_id')->orderBy('sort_order');
    }

    public function galleryImages(): HasMany
    {
        return $this->images()->where('usage', KbImageUsage::Gallery->value);
    }

    public function coverImageUrl(): ?string
    {
        if (! $this->relationLoaded('galleryImages')) {
            return null;
        }

        $img = $this->galleryImages->first();
        if (! $img) {
            return null;
        }

        return route('knowledge-base.images.file', ['image' => $img->id]);
    }

    public function readingTimeMinutes(): int
    {
        $text = strip_tags($this->content ?? '');
        $words = str_word_count($text);

        return (int) max(1, (int) round($words / 200));
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(KbArticleAttachment::class, 'article_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    public function favoritedByAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SystemAccount::class, 'kb_article_favorites', 'article_id', 'system_account_id')
            ->withTimestamps();
    }
}
