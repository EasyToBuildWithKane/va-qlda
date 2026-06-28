<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WeeklyReport
 */
class WeeklyReportResource extends JsonResource
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
            'code' => $this->code(),
            'project_id' => $this->project_id,
            'sprint_id' => $this->sprint_id,
            'sprint_name' => $this->whenLoaded('sprint', fn () => $this->sprint?->name),
            'week_number' => $this->week_number,
            'week_start' => $this->week_start->toDateString(),
            'week_end' => $this->week_end->toDateString(),
            'title' => $this->title,
            'status' => $this->enum($this->status),
            'is_locked' => $this->isLocked(),
            'executive_summary' => $this->executive_summary,
            'ai_summary' => $this->ai_summary,
            'kpi' => $this->kpi_snapshot ?? [],
            'meta' => $this->meta ?? [],
            'sections' => WeeklyReportSectionResource::collection(
                $this->whenLoaded('sections', fn () => $this->sections, collect())
            ),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'generated_by' => $this->whenLoaded('generatedBy', fn () => $this->generatedBy?->display_name),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy?->display_name),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->display_name),
            'reject_reason' => $this->reject_reason,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'submit' => $user->can('submit', $this->resource),
                'approve' => $user->can('approve', $this->resource),
                'export' => $user->can('export', $this->resource),
            ] : null,
        ];
    }
}
