<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BlockerAttachment
 */
class BlockerAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_image' => $this->is_image,
            'url' => $this->url(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => [
                'id' => $this->uploadedBy?->id,
                'name' => $this->uploadedBy?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
