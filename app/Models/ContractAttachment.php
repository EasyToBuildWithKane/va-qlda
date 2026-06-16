<?php

namespace App\Models;

use App\Support\Enums\ContractAttachmentCategory;
use App\Support\GoogleWorkspaceUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $contract_id
 * @property ContractAttachmentCategory $category
 * @property int|null $uploaded_by_id
 * @property int|null $updated_by_id
 * @property string $original_name
 * @property string|null $notes
 * @property string|null $path
 * @property string|null $external_url
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 * @property int $version
 */
class ContractAttachment extends Model
{
    protected $fillable = [
        'contract_id',
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
        'version',
    ];

    protected $casts = [
        'category' => ContractAttachmentCategory::class,
        'is_image' => 'boolean',
        'size' => 'integer',
        'version' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by_id');
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

        if (! filled($this->path)) {
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

        return route('contracts.attachments.file', [
            'contract' => $this->contract_id,
            'attachment' => $this->id,
        ]);
    }

    public function embedUrl(): ?string
    {
        if (! $this->isExternalLink()) {
            return null;
        }

        return GoogleWorkspaceUrl::parse($this->external_url)['embed_url'] ?? null;
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

    /** @return 'image'|'pdf'|'docx'|'external'|'none' */
    public function previewKind(): string
    {
        if ($this->isExternalLink()) {
            return 'external';
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

        return 'none';
    }

    public function canPreviewInline(): bool
    {
        return in_array($this->previewKind(), ['image', 'pdf', 'external'], true);
    }
}
