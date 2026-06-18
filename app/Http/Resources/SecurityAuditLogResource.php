<?php

namespace App\Http\Resources;

use App\Support\Audit\AuditActionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SecurityAuditLog */
class SecurityAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = AuditActionCatalog::describe($this->action);

        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_label' => $meta['label'],
            'severity' => $meta['severity'],
            'module' => $meta['module'],
            'module_label' => $meta['module_label'],
            'icon' => $meta['icon'],
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'actor' => $this->actor
                ? ['id' => $this->actor->id, 'name' => $this->actor->display_name]
                : null,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->locale('vi')->diffForHumans(),
        ];
    }
}
