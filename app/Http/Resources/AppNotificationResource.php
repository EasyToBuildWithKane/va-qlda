<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AppNotification */
class AppNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'title' => $this->title,
            'body' => $this->body,
            'actor_name' => $this->actor_name,
            'actor_account_id' => $this->actor_account_id,
            'project_id' => $this->project_id,
            'sprint_id' => $this->sprint_id,
            'task_id' => $this->task_id,
            'action_url' => $this->action_url,
            'meta' => $this->meta ?? [],
            'is_read' => $this->isRead(),
            'is_critical' => $this->isCritical(),
            'is_admin_feed' => $this->is_admin_feed,
            'read_at' => $this->read_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'assigned_to_account_id' => $this->assigned_to_account_id,
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->locale('vi')->diffForHumans(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ]),
        ];
    }
}
