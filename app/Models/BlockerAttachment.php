<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $blocker_id
 * @property int|null $uploaded_by_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 */
class BlockerAttachment extends Model
{
    protected $fillable = [
        'blocker_id',
        'uploaded_by_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'size' => 'integer',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Blocker::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }

    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->path);
    }

    public function url(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return route('blockers.attachments.file', [
            'blocker' => $this->blocker_id,
            'attachment' => $this->id,
        ]);
    }
}
