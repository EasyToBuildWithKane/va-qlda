<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleImage extends Model
{
    public $timestamps = false;

    protected $table = 'kb_article_images';

    protected $fillable = [
        'article_id',
        'uploaded_by_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'alt_text',
        'sort_order',
        'usage',
        'created_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }
}
