<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CredentialRelation
 */
class CredentialRelationResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relation_type' => $this->enum($this->relation_type),
            'label' => $this->label,
            'target' => $this->whenLoaded('target', fn () => $this->target ? [
                'id' => $this->target->id,
                'name' => $this->target->name,
                'system_category' => $this->enum($this->target->system_category),
            ] : null),
        ];
    }
}
