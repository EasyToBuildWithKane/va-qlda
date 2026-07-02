<?php

namespace App\Models;

use App\Support\Enums\ProposalSignatureRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AiProposalScanSignature extends Model
{
    protected $fillable = [
        'ai_proposal_scan_id',
        'role',
        'signed',
        'signer_name',
        'confidence',
        'image_path',
        'bbox',
        'page',
    ];

    protected $casts = [
        'role' => ProposalSignatureRole::class,
        'signed' => 'boolean',
        'confidence' => 'float',
        'bbox' => 'array',
        'page' => 'integer',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(AiProposalScan::class, 'ai_proposal_scan_id');
    }

    public function imageExists(): bool
    {
        return $this->image_path !== null
            && Storage::disk('public')->exists($this->image_path);
    }

    public function imageUrl(): ?string
    {
        if (! $this->imageExists()) {
            return null;
        }

        return route('api.ai-accounts.proposal-scans.signatures.file', [
            'scan' => $this->ai_proposal_scan_id,
            'signature' => $this->id,
        ]);
    }
}
