<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Credential
 */
class CredentialListResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'credential_type' => $this->enum($this->credential_type),
            'system_category' => $this->enum($this->system_category),
            'login_url' => $this->login_url,
            'username' => $this->username,
            'environment' => $this->enum($this->environment),
            'status' => $this->enum($this->status),
            'provider_name' => $this->provider_name,
            'is_shared' => $this->is_shared,
            'is_critical' => $this->is_critical,
            'badges' => $this->badges ?? [],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'display_name' => $this->owner->display_name,
            ] : null),
        ];
    }
}
