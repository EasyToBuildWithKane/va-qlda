<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WeeklyReportSection
 */
class WeeklyReportSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'section' => $this->section->value,
            'label' => $this->section->label(),
            'icon' => $this->section->icon(),
            'editable' => $this->section->isEditable(),
            'content' => $this->content,
            'is_edited' => (bool) $this->is_edited,
            'sort_order' => $this->sort_order,
        ];
    }
}
