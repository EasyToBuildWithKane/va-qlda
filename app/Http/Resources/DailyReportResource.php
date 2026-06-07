<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\DailyReport\Models\DailyReport
 */
class DailyReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'date' => $this->date->toDateString(),
            'projects' => $this->projects ?? [],
            'title' => $this->title,
            'goals_today' => $this->goals_today,
            'progress_update' => $this->progress_update,
            'results_impact' => $this->results_impact,
            'blockers' => $this->blockers,
            'improvement_suggestions' => $this->improvement_suggestions,
            'highlights' => $this->highlights,
            'plan_tomorrow' => $this->plan_tomorrow,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_late' => $this->is_late,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,

            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
                'avatar_path' => PublicMediaUrl::fromPublicDisk($this->employee->avatar_path),
            ]),
            'score' => $this->when(
                $this->relationLoaded('score'),
                fn () => $this->score ? new DailyReportScoreResource($this->score) : null,
            ),

            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'submit' => $user->can('submit', $this->resource),
                'score' => $user->can('score', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
