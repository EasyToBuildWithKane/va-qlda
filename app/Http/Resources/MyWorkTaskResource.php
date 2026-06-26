<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Enums\TaskStatus;
use App\Support\TaskCompletion;
use App\Support\TaskTimeliness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props gọn cho card màn hình "Việc của tôi" — đa dự án, một dòng/việc.
 *
 * Khác TaskResource (vốn nặng, dùng cho panel chi tiết): thêm chip dự án + cờ
 * quyền thao tác (`can.contribute`, `can.act_team`) đã tính sẵn (batch) trong
 * MyWorkQuery để tránh N+1 — resource chỉ đọc transient attribute.
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

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'status' => $this->enum($this->status),
            'priority' => $this->enum($this->priority),
            'phase' => $this->enum($this->phase),
            'is_milestone' => (bool) $this->is_milestone,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_late' => TaskTimeliness::isLate($this->resource),
            'estimate_hours' => $this->estimate_hours !== null ? (float) $this->estimate_hours : null,
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
            'assignee' => $this->whenLoaded('assignee', fn () => $this->person($this->assignee)),
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
