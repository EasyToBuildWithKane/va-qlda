<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\BlockerRecheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Blocker
 */
class BlockerResource extends JsonResource
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
            'code' => $this->code ?? ('RSK-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT)),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
                'color' => $this->project->color,
            ] : null),
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ] : null),
            'title' => $this->title,
            'description' => $this->description,
            'root_cause' => $this->root_cause,
            'severity' => $this->enum($this->severity),
            'status' => $this->enum($this->status),
            'raised_by' => $this->whenLoaded('raisedBy', fn () => $this->person($this->raisedBy)),
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'raised_at' => $this->raised_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'recheck_result' => $this->recheck_result ? $this->enum($this->recheck_result) : null,
            'recheck_note' => $this->recheck_note,
            'rechecked_at' => $this->rechecked_at?->toIso8601String(),
            'rechecked_by' => $this->whenLoaded('recheckedBy', fn () => $this->person($this->recheckedBy)),
            'needs_recheck' => BlockerRecheck::needsRecheck($this->resource),
            'resolution' => $this->resolution,
            'evidence_links' => $this->evidence_links ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_overdue' => $this->due_date
                && $this->due_date->isPast()
                && ! $this->status->isTerminal(),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => $this->whenLoaded('comments', fn () => CommentResource::collection($this->comments)->resolve()),
            'attachments' => $this->whenLoaded('attachments', fn () => BlockerAttachmentResource::collection($this->attachments)->resolve()),
            'activities' => $this->whenLoaded('activities', fn () => BlockerActivityResource::collection($this->activities)->resolve()),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'comment' => $user->can('comment', $this->resource),
                'recheck' => $user->can('recheck', $this->resource) && BlockerRecheck::needsRecheck($this->resource),
            ] : null,
        ];
    }
}
