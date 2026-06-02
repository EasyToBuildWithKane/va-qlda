<?php

namespace App\Http\Resources;

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
            'body' => $this->body,
            'author' => [
                'id' => $this->employee_id,
                'name' => $this->authorName(),
                'avatar_path' => $this->author?->avatar_path,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
