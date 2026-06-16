<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Credential
 */
class CredentialResource extends JsonResource
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
            'name' => $this->name,
            'credential_type' => $this->enum($this->credential_type),
            'system_category' => $this->enum($this->system_category),
            'login_url' => $this->login_url,
            'username' => $this->username,
            'has_password' => (bool) $this->login_password,
            'email' => $this->email,
            'phone' => $this->phone,
            'provider_name' => $this->provider_name,
            'description' => $this->description,
            'notes' => $this->notes,
            'project_id' => $this->project_id,
            'department_id' => $this->department_id,
            'owner_id' => $this->owner_id,
            'environment' => $this->enum($this->environment),
            'status' => $this->enum($this->status),
            'mfa_enabled' => $this->mfa_enabled,
            'recovery_email' => $this->recovery_email,
            'recovery_phone' => $this->recovery_phone,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'password_expires_at' => $this->password_expires_at?->toIso8601String(),
            'is_shared' => $this->is_shared,
            'is_critical' => $this->is_critical,
            'badges' => $this->badges ?? [],
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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
            'access_grants' => $this->when(
                $this->relationLoaded('accessGrants'),
                fn () => CredentialAccessGrantResource::collection($this->accessGrants)->resolve(),
            ),
            'outgoing_relations' => CredentialRelationResource::collection($this->whenLoaded('outgoingRelations')),
            'password_histories' => CredentialPasswordHistoryResource::collection($this->whenLoaded('passwordHistories')),
            'can' => $user ? [
                'view' => $user->can('view', $this->resource),
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'view_password' => $user->can('viewPassword', $this->resource),
                'share' => $user->can('share', $this->resource),
                'manage_access' => $user->can('manageAccess', $this->resource),
                'view_access_tab' => $user->can('viewAccessTab', $this->resource),
                'export' => $user->can('export', $this->resource),
            ] : null,
        ];
    }
}
