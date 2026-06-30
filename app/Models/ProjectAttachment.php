<?php

namespace App\Models;

use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\GoogleWorkspaceUrl;
use App\Support\ProjectAttachmentExternalUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $project_id
 * @property ProjectAttachmentCategory $category
 * @property int|null $uploaded_by_id
 * @property int|null $updated_by_id
 * @property string $original_name
 * @property string|null $notes
 * @property string $path
 * @property string|null $external_url
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 */
class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id',
        'category',
        'uploaded_by_id',
        'updated_by_id',
        'original_name',
        'notes',
        'path',
        'external_url',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'category' => ProjectAttachmentCategory::class,
        'is_image' => 'boolean',
        'size' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectAttachmentActivity::class)->latest();
    }

    public function isExternalLink(): bool
    {
        return filled($this->external_url);
    }

    public function fileExists(): bool
    {
        if ($this->isExternalLink()) {
            return true;
        }

        if ($this->path === '') {
            return false;
        }

        return Storage::disk('public')->exists($this->path);
    }

    public function url(): ?string
    {
        if ($this->isExternalLink()) {
            return $this->external_url;
        }

        if (! $this->fileExists()) {
            return null;
        }

        return route('projects.attachments.file', [
            'project' => $this->project_id,
            'attachment' => $this->id,
        ]);
    }

    public function embedUrl(): ?string
    {
        if (! $this->isExternalLink()) {
            return null;
        }

        $parsed = ProjectAttachmentExternalUrl::parse($this->external_url);

        return $parsed['embed_url'] ?? null;
    }

    public function isGoogleDocument(): bool
    {
        if (! $this->isExternalLink()) {
            return false;
        }

        return (GoogleWorkspaceUrl::parse($this->external_url)['type'] ?? null) === 'document';
    }

    public function isGoogleSpreadsheet(): bool
    {
        if (! $this->isExternalLink()) {
            return false;
        }

        return (GoogleWorkspaceUrl::parse($this->external_url)['type'] ?? null) === 'spreadsheet';
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_ends_with(strtolower($this->original_name), '.pdf');
    }

    public function isDocx(): bool
    {
        $name = strtolower($this->original_name);

        return str_ends_with($name, '.docx')
            || $this->mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function isSpreadsheet(): bool
    {
        $name = strtolower($this->original_name);

        return str_ends_with($name, '.xlsx')
            || str_ends_with($name, '.xls')
            || in_array($this->mime_type, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ], true);
    }

    /** @return 'image'|'pdf'|'docx'|'xlsx'|'doc-legacy'|'google_doc'|'google_sheet'|'none' */
    public function previewKind(): string
    {
        if ($this->isGoogleDocument()) {
            return 'google_doc';
        }
        if ($this->isGoogleSpreadsheet()) {
            return 'google_sheet';
        }
        if ($this->is_image) {
            return 'image';
        }
        if ($this->isPdf()) {
            return 'pdf';
        }
        if ($this->isDocx()) {
            return 'docx';
        }
        if ($this->isSpreadsheet()) {
            return 'xlsx';
        }
        if (str_ends_with(strtolower($this->original_name), '.doc')) {
            return 'doc-legacy';
        }

        return 'none';
    }

    public function canPreviewInline(): bool
    {
        return in_array($this->previewKind(), ['image', 'pdf', 'docx', 'xlsx', 'google_doc', 'google_sheet'], true);
    }
}
