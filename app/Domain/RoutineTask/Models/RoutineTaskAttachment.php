<?php

namespace App\Domain\RoutineTask\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $routine_task_id
 * @property int|null $uploaded_by_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 */
class RoutineTaskAttachment extends Model
{
    protected $fillable = [
        'routine_task_id',
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

    public function routineTask(): BelongsTo
    {
        return $this->belongsTo(RoutineTask::class);
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

        return route('routine-tasks.attachments.file', [
            'routineTask' => $this->routine_task_id,
            'attachment' => $this->id,
        ]);
    }
}
