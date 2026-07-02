<?php

namespace App\Models;

use App\Support\Enums\AiProposalScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AiProposalScan extends Model
{
    use HasUuids;

    protected $fillable = [
        'ai_purchase_proposal_id',
        'original_path',
        'original_name',
        'mime_type',
        'size',
        'status',
        'extracted_fields',
        'raw_text',
        'error_message',
        'pages',
        'duration_ms',
        'created_by',
    ];

    protected $casts = [
        'status' => AiProposalScanStatus::class,
        'extracted_fields' => 'array',
        'size' => 'integer',
        'pages' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'created_by');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(AiPurchaseProposal::class, 'ai_purchase_proposal_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(AiProposalScanSignature::class);
    }

    public function fileExists(): bool
    {
        return $this->original_path !== null
            && Storage::disk('public')->exists($this->original_path);
    }

    public function fileUrl(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return route('api.ai-accounts.proposal-scans.file', ['scan' => $this->id]);
    }
}
