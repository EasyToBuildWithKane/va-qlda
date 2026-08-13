<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TestCaseAttachment
 */
class TestCaseAttachmentResource extends JsonResource
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
            'file_available' => $this->fileExists(),
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
