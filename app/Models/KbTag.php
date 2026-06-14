<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KbTag extends Model
{
    protected $table = 'kb_tags';

    protected $fillable = ['name', 'slug', 'color'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tags', 'tag_id', 'article_id');
    }
}
