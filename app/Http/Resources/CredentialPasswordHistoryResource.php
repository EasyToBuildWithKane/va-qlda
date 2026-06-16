<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CredentialPasswordHistory
 */
class CredentialPasswordHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'changed_at' => $this->changed_at?->toIso8601String(),
            'notes' => $this->notes,
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'display_name' => $this->changedBy->display_name,
            ] : null),
        ];
    }
}
