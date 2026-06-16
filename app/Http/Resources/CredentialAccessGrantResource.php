<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CredentialAccessGrant
 */
class CredentialAccessGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'permissions' => $this->permissions ?? [],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'display_name' => $this->account->display_name,
                'username' => $this->account->username,
            ] : null),
        ];
    }
}
