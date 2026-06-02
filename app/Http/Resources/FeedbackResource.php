<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Feedback
 */
class FeedbackResource extends JsonResource
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
            'code' => $this->code,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'color' => $this->project->color,
            ] : null),
            'category' => $this->enum($this->category),
            'title' => $this->title,
            'description' => $this->description,
            'rating' => $this->rating,
            'priority' => $this->enum($this->priority),
            'status' => $this->enum($this->status),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->person($this->reporter)),
            'reporter_name' => $this->reporter_name,
            'reporter_email' => $this->reporter_email,
            'reporter_display' => $this->reporterName(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->person($this->assignee)),
            'assignee_id' => $this->assignee_id,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
