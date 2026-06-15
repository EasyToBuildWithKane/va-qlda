<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CongngheSoftwareProposalAttachment
 */
class CongngheSoftwareProposalAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proposalId = $this->congnghe_software_proposal_id;
        $available = $this->fileExists();

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_image' => $this->is_image,
            'file_available' => $available,
            'url' => $available
                ? route('congnghe.proposals.attachments.file', [
                    'proposal' => $proposalId,
                    'attachment' => $this->id,
                ])
                : null,
        ];
    }
}
