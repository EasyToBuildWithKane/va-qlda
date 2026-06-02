<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskActivity
 */
class TaskActivityResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'meta' => $this->meta,
            'employee' => $this->whenLoaded('employee', fn () => $this->person($this->employee)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
