<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskAttachment
 */
class TaskAttachmentResource extends JsonResource
{
    use PresentsEntities;

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
            'version' => $this->version,
            'url' => $this->url(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->person($this->uploadedBy)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
