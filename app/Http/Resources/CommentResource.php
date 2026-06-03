<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'reactions' => $this->reactions ?? [],
            'author' => [
                'id' => $this->employee_id,
                'name' => $this->authorName(),
                'avatar_path' => PublicMediaUrl::fromPublicDisk($this->author?->avatar_path),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'replies' => $this->whenLoaded(
                'replies',
                fn () => CommentResource::collection($this->replies->filter())->resolve(),
            ),
        ];
    }
}
