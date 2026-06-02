<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_attachment_id
 * @property int|null $employee_id
 * @property string $event
 * @property string $description
 * @property array<string, mixed>|null $meta
 */
class ProjectAttachmentActivity extends Model
{
    protected $fillable = [
        'project_attachment_id',
        'employee_id',
        'event',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ProjectAttachment::class, 'project_attachment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
