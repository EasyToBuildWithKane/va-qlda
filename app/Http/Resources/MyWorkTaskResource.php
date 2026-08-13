<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Enums\TaskHoursTiming;
use App\Support\Enums\TaskSlaResult;
use App\Support\Enums\TaskSource;
use App\Support\Enums\TaskStatus;
use App\Support\TaskCompletion;
use App\Support\TaskTimeliness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props cho card + modal chi tiết màn hình "Việc của tôi" — đa dự án.
 *
 * Khác TaskResource (panel dự án đầy đủ): đủ meta đọc trên modal /my-work,
 * kèm chip dự án + cờ quyền (`can.contribute`, `can.act_team`) đã tính sẵn
 * (batch) trong MyWorkQuery để tránh N+1 — resource chỉ đọc transient attribute.
 *
 * @mixin \App\Models\Task
 */
class MyWorkTaskResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isDone = $this->status === TaskStatus::Done;
        $mayUnlock = $user !== null && TaskCompletion::actorMayUnlockStatus($user);

        $source = $this->source instanceof TaskSource
            ? $this->source
            : (is_string($this->source) ? TaskSource::tryFrom($this->source) : null);

        $hoursTiming = is_string($this->hours_timing) ? TaskHoursTiming::tryFrom($this->hours_timing) : null;
        $slaResult = is_string($this->sla_result) ? TaskSlaResult::tryFrom($this->sla_result) : null;

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->enum($this->status),
            'priority' => $this->enum($this->priority),
            'phase' => $this->enum($this->phase),
            'is_milestone' => (bool) $this->is_milestone,
            'source' => $source ? [
                'value' => $source->value,
                'label' => $source->label(),
            ] : null,
            'start_date' => $this->start_date?->toDateString(),
            'work_started_at' => $this->work_started_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_late' => TaskTimeliness::isLate($this->resource),
            'estimate_hours' => $this->estimate_hours !== null ? (float) $this->estimate_hours : null,
            'actual_hours' => $this->actual_hours !== null ? (float) $this->actual_hours : null,
            'story_points' => $this->story_points !== null ? (float) $this->story_points : null,
            'completion_note' => $this->completion_note,
            'hours_timing' => $hoursTiming ? $this->enum($hoursTiming) : null,
            'sla_result' => $slaResult ? $this->enum($slaResult) : null,
            'progress' => (int) $this->progress,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
                'color' => $this->project->color,
            ] : null),
            'sprint' => $this->whenLoaded('sprint', fn () => $this->sprint ? [
                'id' => $this->sprint->id,
                'name' => $this->sprint->name,
            ] : null),
            'epic' => $this->whenLoaded('epic', fn () => $this->epic ? [
                'id' => $this->epic->id,
                'name' => $this->epic->name,
                'color' => $this->epic->color,
            ] : null),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->person($this->assignee)),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->person($this->reporter)),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->person($this->reviewer)),
            // Giờ đã ghi *hôm nay* trên việc này (worklogs eager-load đã lọc theo ngày + người).
            'logged_today' => $this->whenLoaded('worklogs', fn () => round((float) $this->worklogs->sum('hours'), 2)),
            'can' => [
                'contribute' => (bool) ($this->can_contribute ?? false),
                'act_team' => (bool) ($this->can_act_team ?? false),
            ],
            'can_change_status' => ! $isDone || $mayUnlock,
        ];
    }
}
