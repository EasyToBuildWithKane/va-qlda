<?php

namespace App\Http\Resources;

use App\Support\Enums\ReportStatus;
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
        $isOwner = $user !== null && $user->employee_id === $this->employee_id;

        $taskStatusSnapshot = collect($this->task_status_snapshot ?? []);

        if (! $isOwner) {
            $taskStatusSnapshot = $taskStatusSnapshot
                ->filter(fn (mixed $entry) => is_array($entry) && empty($entry['synced_after_submit']))
                ->values();
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'date' => $this->date->toDateString(),
            'projects' => $this->projects ?? [],
            'title' => $this->title,
            'goals_today' => $this->goals_today,
            'progress_update' => $this->progress_update,
            'blockers' => $this->blockers,
            'improvement_suggestions' => $this->improvement_suggestions,
            'plan_tomorrow' => $this->plan_tomorrow,
            'task_status_snapshot' => $taskStatusSnapshot->all(),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_late' => $this->is_late,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'recalled_at' => $this->recalled_at?->toIso8601String(),
            'recall_count' => (int) $this->recall_count,
            'review_notes' => $this->review_notes,

            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
                'role_title' => $this->employee->role_title,
                'avatar_path' => PublicMediaUrl::fromPublicDisk($this->employee->avatar_path),
            ]),
            'score' => $this->when(
                $this->relationLoaded('score'),
                fn () => $this->score
                    ? (new DailyReportScoreResource($this->score))->resolve()
                    : null,
            ),

            'has_feedback' => $this->hasReviewerFeedback(),

            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'submit' => $user->can('submit', $this->resource),
                'recall' => $user->can('recall', $this->resource),
                'score' => $user->can('score', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }

    private function hasReviewerFeedback(): bool
    {
        if ($this->status === ReportStatus::Draft && filled($this->review_notes)) {
            return true;
        }

        if ($this->relationLoaded('score') && filled($this->score?->notes)) {
            return true;
        }

        return false;
    }
}
