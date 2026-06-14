<?php

namespace App\Support\KnowledgeBase;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KbArticleSearch
{
    public static function apply(Builder $query, string $search): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        if (self::canUseFulltext($query)) {
            $query->whereRaw(
                'MATCH(title, excerpt, content) AGAINST (? IN NATURAL LANGUAGE MODE)',
                [$term],
            );

            return;
        }

        $escaped = str_replace(['%', '_'], ['\%', '\_'], $term);
        $like = '%'.$escaped.'%';
        $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }

    private static function canUseFulltext(Builder $query): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $table = $query->getModel()->getTable();

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_type', 'FULLTEXT')
            ->exists();
    }
}
