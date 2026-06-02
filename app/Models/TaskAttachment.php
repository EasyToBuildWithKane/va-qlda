<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $task_id
 * @property int|null $uploaded_by_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 * @property int $version
 */
class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id',
        'uploaded_by_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'is_image',
        'version',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'size' => 'integer',
        'version' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
