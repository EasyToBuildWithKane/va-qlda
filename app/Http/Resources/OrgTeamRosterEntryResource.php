<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class OrgTeamRosterEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;
        $assignments = $row['assignments'] ?? [];

        $isLeader = false;
        foreach ($assignments as $assignment) {
            if (! empty($assignment['is_leader'])) {
                $isLeader = true;
                break;
            }
        }

        $primaryOrg = $assignments[0]['path'] ?? null;

        return [
            'id' => $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role_title' => $row['role_title'],
            'avatar_path' => $row['avatar_path'],
            'is_active' => (bool) ($row['is_active'] ?? true),
            'is_leader' => $isLeader,
            'primary_org' => $primaryOrg,
            'assignments' => $assignments,
        ];
    }
}
