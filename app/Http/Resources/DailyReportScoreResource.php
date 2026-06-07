<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\DailyReport\Models\DailyReportScore
 */
class DailyReportScoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'task_completion' => (float) $this->task_completion,
            'skill_score' => (float) $this->skill_score,
            'attitude_score' => (float) $this->attitude_score,
            'kaizen_score' => (float) $this->kaizen_score,
            'expertise_score' => (float) $this->expertise_score,
            'total_score' => (float) $this->total_score,
            'grade' => $this->grade->value,
            'grade_label' => $this->grade->label(),
            'grade_color' => $this->grade->color(),
            'notes' => $this->notes,
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->full_name,
            ]),
        ];
    }
}
