<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bản nhẹ cho sidebar timeline (danh sách tuần).
 *
 * @mixin \App\Models\WeeklyReport
 */
class WeeklyReportListResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code(),
            'sprint_id' => $this->sprint_id,
            'week_number' => $this->week_number,
            'week_start' => $this->week_start->toDateString(),
            'week_end' => $this->week_end->toDateString(),
            'period_label' => $this->periodLabel(),
            'status' => $this->enum($this->status),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
