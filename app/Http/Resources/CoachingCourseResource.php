<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CoachingCourse
 */
class CoachingCourseResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'objectives' => $this->objectives,
            'student_name' => $this->student_name,
            'coach_name' => $this->coach_name,
            'status' => $this->enum($this->status),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'total_fee' => $this->total_fee !== null ? (float) $this->total_fee : null,
            'hourly_rate' => $this->hourly_rate !== null ? (float) $this->hourly_rate : null,
            'total_hours' => $this->total_hours !== null ? (float) $this->total_hours : null,
            'progress_percent' => $this->when(
                isset($this->progress_percent_computed),
                (int) ($this->progress_percent_computed ?? $this->progressPercent()),
            ),
            'student' => $this->whenLoaded('student', fn () => $this->person($this->student)),
            'student_id' => $this->student_id,
            'student_display' => $this->student_name ?: $this->student?->full_name,
            'coach' => $this->whenLoaded('coach', fn () => $this->person($this->coach)),
            'coach_id' => $this->coach_id,
            'coach_display' => $this->coach_name ?: $this->coach?->full_name,
            'sessions' => CoachingSessionResource::collection($this->whenLoaded('sessions')),
            'sessions_count' => $this->whenCounted('sessions'),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : [],
        ];
    }
}
