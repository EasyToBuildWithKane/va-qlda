<?php

namespace App\Support\KnowledgeBase;

use App\Models\KbArticle;
use App\Models\KbTag;
use Illuminate\Support\Str;

class KbTagSync
{
    /**
     * @param  array<int, string>|null  $names
     */
    public static function sync(KbArticle $article, ?array $names): void
    {
        if ($names === null) {
            return;
        }

        $ids = [];
        foreach ($names as $raw) {
            $name = trim($raw);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $tag = KbTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
            $ids[] = $tag->id;
        }

        $article->tags()->sync($ids);
    }
}
