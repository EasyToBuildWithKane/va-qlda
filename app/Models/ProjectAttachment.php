<?php

namespace App\Models;

use App\Support\Enums\ProjectAttachmentCategory;
use App\Support\GoogleWorkspaceUrl;
use App\Support\ProjectAttachmentExternalUrl;
use App\Support\ProjectAttachmentNewFile;
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
 * @property int|null $parent_id
 * @property bool $is_folder
 */
class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id',
        'category',
        'parent_id',
        'is_folder',
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
        'is_folder' => 'boolean',
        'size' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('is_folder', 'desc')->orderBy('original_name');
    }

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
        return ! $this->is_folder && filled($this->external_url);
    }

    public function isFolder(): bool
    {
        return (bool) $this->is_folder;
    }

    public function fileExists(): bool
    {
        if ($this->isFolder()) {
            return true;
        }

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
        if ($this->isFolder()) {
            return null;
        }

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

    public function isTextEditable(): bool
    {
        if ($this->isFolder() || $this->isExternalLink() || $this->path === '') {
            return false;
        }

        return ProjectAttachmentNewFile::isTextEditableName($this->original_name, $this->mime_type);
    }

    /** @return 'image'|'pdf'|'docx'|'xlsx'|'text'|'markdown'|'html'|'doc-legacy'|'google_doc'|'google_sheet'|'none' */
    public function previewKind(): string
    {
        if ($this->isFolder()) {
            return 'none';
        }

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

        $ext = strtolower((string) pathinfo($this->original_name, PATHINFO_EXTENSION));
        if ($ext === 'md') {
            return 'markdown';
        }
        if (in_array($ext, ['html', 'htm'], true)) {
            return 'html';
        }

        if ($this->isTextEditable()) {
            return 'text';
        }
        if (str_ends_with(strtolower($this->original_name), '.doc')) {
            return 'doc-legacy';
        }

        return 'none';
    }

    public function canPreviewInline(): bool
    {
        return in_array($this->previewKind(), [
            'image', 'pdf', 'docx', 'xlsx', 'text', 'markdown', 'html', 'google_doc', 'google_sheet',
        ], true);
    }

    /**
     * First lines of a text-editable file for grid thumbnails.
     * Skips folders, links, non-text, missing, or oversized files (>256 KB).
     */
    public function previewSnippet(int $maxChars = 400, int $maxLines = 8): ?string
    {
        if (! $this->isTextEditable() || ! $this->fileExists()) {
            return null;
        }

        $disk = Storage::disk('public');
        $size = (int) $disk->size($this->path);
        if ($size <= 0 || $size > 256 * 1024) {
            return null;
        }

        $raw = $disk->get($this->path);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $normalized);
        $slice = array_slice($lines, 0, max(1, $maxLines));
        $snippet = implode("\n", $slice);
        $snippet = trim($snippet);

        if ($snippet === '') {
            return null;
        }

        if (mb_strlen($snippet) > $maxChars) {
            $snippet = rtrim(mb_substr($snippet, 0, $maxChars)).'…';
        }

        return $snippet;
    }
}
