<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ContractAttachment
 */
class ContractAttachmentResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'category' => $this->enum($this->category),
            'original_name' => $this->original_name,
            'notes' => $this->notes,
            'is_external' => $this->isExternalLink(),
            'external_url' => $this->external_url,
            'url' => $this->url(),
            'embed_url' => $this->embedUrl(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_image' => $this->is_image,
            'version' => $this->version,
            'preview_kind' => $this->previewKind(),
            'can_preview' => $this->canPreviewInline(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->person($this->uploadedBy)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
