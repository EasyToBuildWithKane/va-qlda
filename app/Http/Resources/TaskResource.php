<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\TaskTimeliness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'sprint_id' => $this->sprint_id,
            'parent_id' => $this->parent_id,
            'epic_id' => $this->epic_id,
            'sprint' => $this->whenLoaded('sprint', fn () => $this->sprint ? [
                'id' => $this->sprint->id,
                'name' => $this->sprint->name,
            ] : null),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->enum($this->status),
            'priority' => $this->enum($this->priority),
            'phase' => $this->phase ? ['value' => $this->phase->value, 'label' => $this->phase->label()] : null,
            'is_milestone' => (bool) $this->is_milestone,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->person($this->assignee)),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees
                ->map(fn ($e) => $this->person($e))
                ->filter()
                ->values()),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->person($this->reporter)),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->person($this->reviewer)),
            'start_date' => $this->start_date?->toDateString(),
            'work_started_at' => $this->work_started_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_late' => TaskTimeliness::isLate($this->resource),
            'late_reasons' => TaskTimeliness::lateReasons($this->resource),
            'estimate_deadline_at' => TaskTimeliness::estimateDeadline($this->resource)?->toIso8601String(),
            'estimate_hours' => $this->estimate_hours !== null ? (float) $this->estimate_hours : null,
            'story_points' => $this->story_points !== null ? (float) $this->story_points : null,
            'epic' => $this->whenLoaded('epic', fn () => $this->epic ? (new EpicResource($this->epic))->resolve() : null),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
            ] : null),
            'progress' => (int) $this->progress,
            'order_column' => $this->order_column,
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => ['value' => $t->status->value, 'label' => $t->status->label(), 'color' => $t->status->color()],
            ])->values()),
            'dependents' => $this->whenLoaded('dependents', fn () => $this->dependents->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => ['value' => $t->status->value, 'label' => $t->status->label(), 'color' => $t->status->color()],
                'progress' => (int) $t->progress,
            ])->values()),
            'logged_hours' => $this->whenLoaded('worklogs', fn () => (float) $this->worklogs->sum('hours')),
            'labor_cost' => $this->whenLoaded('worklogs', fn () => (float) $this->worklogs->sum('cost')),
            'worklogs' => $this->whenLoaded('worklogs', fn () => $this->worklogs->map(fn ($w) => [
                'id' => $w->id,
                'date' => $w->date->toDateString(),
                'hours' => (float) $w->hours,
                'note' => $w->note,
                'cost' => $w->cost !== null ? (float) $w->cost : null,
                'employee' => $w->relationLoaded('employee') && $w->employee
                    ? ['id' => $w->employee->id, 'name' => $w->employee->name, 'avatar_path' => $w->employee->avatar_path]
                    : null,
            ])->values()),
            'comments' => $this->whenLoaded('comments', function () {
                $roots = $this->comments
                    ->whereNull('parent_id')
                    ->sortByDesc('created_at')
                    ->values();

                return CommentResource::collection($roots)->resolve();
            }),
            'subtasks' => $this->whenLoaded('subtasks', fn () => $this->subtasks
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => ['value' => $t->status->value, 'label' => $t->status->label(), 'color' => $t->status->color()],
                    'progress' => (int) $t->progress,
                    'estimate_hours' => $t->estimate_hours !== null ? (float) $t->estimate_hours : null,
                    'assignee' => $t->relationLoaded('assignee') && $t->assignee
                        ? $this->person($t->assignee)
                        : null,
                ])
                ->filter(fn ($row) => $row !== null)
                ->values()),
            'watchers' => $this->whenLoaded('watchers', fn () => $this->watchers
                ->map(fn ($e) => $this->person($e))
                ->filter()
                ->values()),
            'attachments' => $this->whenLoaded('attachments', fn () => TaskAttachmentResource::collection($this->attachments)->resolve()),
            'activities' => $this->whenLoaded('activities', fn () => TaskActivityResource::collection($this->activities)->resolve()),
        ];
    }
}
