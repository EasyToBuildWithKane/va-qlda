<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CongngheSoftwareProposal
 */
class CongngheSoftwareProposalResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'submitter_name' => $this->submitter_name,
            'submitter_email' => $this->submitter_email,
            'department' => $this->department,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->enum($this->status),
            'email_sent_at' => $this->email_sent_at?->toIso8601String(),
            'email_error' => $this->email_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'attachments_count' => $this->whenCounted('attachments'),
            'attachments' => CongngheSoftwareProposalAttachmentResource::collection($this->whenLoaded('attachments')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
            ] : ['update' => false],
        ];
    }
}
