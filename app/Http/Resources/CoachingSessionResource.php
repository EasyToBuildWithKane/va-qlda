<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Coaching\SafeEmbedUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CoachingSession
 */
class CoachingSessionResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'session_number' => $this->session_number,
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_hours' => $this->total_hours !== null ? (float) $this->total_hours : null,
            'topic' => $this->topic,
            'content' => $this->content,
            'notes' => $this->notes,
            'status' => $this->enum($this->status),
            'materials' => $this->whenLoaded('materials', fn () => $this->materials->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'title' => $m->title,
                'url' => $m->url,
                'embedSrc' => $m->url ? SafeEmbedUrl::embedSrc($m->url) : null,
                'embedAllowed' => $m->url ? SafeEmbedUrl::isAllowed($m->url) : false,
                'file_url' => $m->path ? route('coaching.materials.file', ['material' => $m->id]) : null,
            ])),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'deadline' => $a->deadline?->toIso8601String(),
                'priority' => $a->priority->value,
                'status' => $a->status->value,
                'github_url' => $a->github_url,
                'notes' => $a->notes,
            ])),
        ];
    }
}
