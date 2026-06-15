<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $congnghe_software_proposal_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 */
class CongngheSoftwareProposalAttachment extends Model
{
    protected $fillable = [
        'congnghe_software_proposal_id',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(CongngheSoftwareProposal::class, 'congnghe_software_proposal_id');
    }

    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->path);
    }
}
