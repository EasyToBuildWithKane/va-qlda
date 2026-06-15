<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Models\KbArticleImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\KbArticle
 */
class KbArticleResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->enum($this->status),
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => (new KbCategoryResource($this->category))->resolve()),
            'category_id' => $this->category_id,
            'author' => $this->whenLoaded('author', fn () => $this->person($this->author)),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size' => $a->size,
                'url' => route('knowledge-base.attachments.file', ['attachment' => $a->id]),
            ])),
            'gallery_images' => $this->whenLoaded('galleryImages', function () {
                return $this->galleryImages->map(function ($img) {
                    /** @var KbArticleImage $img */
                    return [
                        'id' => $img->id,
                        'alt_text' => $img->alt_text,
                        'sort_order' => $img->sort_order,
                        'url' => route('knowledge-base.images.file', ['image' => $img->id]),
                        'original_name' => $img->original_name,
                    ];
                });
            }),
            'comments_count' => $this->whenCounted('comments'),
            'cover_url' => $this->when(
                $this->relationLoaded('galleryImages'),
                fn () => $this->coverImageUrl(),
            ),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'is_favorite' => $this->when(property_exists($this->resource, 'is_favorite'), (bool) $this->resource->is_favorite),
            'is_read' => $this->when(property_exists($this->resource, 'is_read'), (bool) $this->resource->is_read),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'publish' => $user->can('publish', $this->resource),
            ] : [],
        ];
    }
}
