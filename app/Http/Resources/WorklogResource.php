<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Worklog
 */
class WorklogResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_title' => $this->whenLoaded('task', fn () => $this->task->title),
            'employee' => $this->whenLoaded('employee', fn () => $this->person($this->employee)),
            'date' => $this->date->toDateString(),
            'hours' => (float) $this->hours,
            'rate_snapshot' => $this->rate_snapshot !== null ? (float) $this->rate_snapshot : null,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'note' => $this->note,
        ];
    }
}
