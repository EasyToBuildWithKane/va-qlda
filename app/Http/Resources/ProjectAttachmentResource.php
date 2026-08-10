<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProjectAttachment
 */
class ProjectAttachmentResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'category_label' => $this->category->labelVi(),
            'parent_id' => $this->parent_id,
            'is_folder' => $this->isFolder(),
            'original_name' => $this->original_name,
            'notes' => $this->notes,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'is_image' => $this->is_image,
            'is_pdf' => $this->isPdf(),
            'is_docx' => $this->isDocx(),
            'is_spreadsheet' => $this->isSpreadsheet(),
            'preview_kind' => $this->previewKind(),
            'can_preview' => $this->canPreviewInline(),
            'can_edit_content' => $this->isTextEditable(),
            'preview_snippet' => $this->previewSnippet(),
            'file_available' => $this->fileExists(),
            'is_external_link' => $this->isExternalLink(),
            'is_google_doc' => $this->isGoogleDocument(),
            'is_google_sheet' => $this->isGoogleSpreadsheet(),
            'embed_url' => $this->embedUrl(),
            'url' => $this->url(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->person($this->uploadedBy)),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->person($this->updatedBy)),
            'activities' => ProjectAttachmentActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
