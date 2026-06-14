<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleAttachment extends Model
{
    protected $table = 'kb_article_attachments';

    protected $fillable = [
        'article_id',
        'uploaded_by_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'article_id');
    }
}
