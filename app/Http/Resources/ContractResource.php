<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contract
 */
class ContractResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $daysUntilExpiry = $this->daysUntilExpiry();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'vendor_id' => $this->vendor_id,
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'code' => $this->vendor->code,
            ] : null),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'department_id' => $this->department_id,
            'using_unit' => $this->using_unit,
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),

            'currency' => $this->currency,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'monthly_cost' => $this->monthly_cost !== null ? (float) $this->monthly_cost : null,
            'annual_cost' => $this->annual_cost !== null ? (float) $this->annual_cost : null,
            'lifecycle_cost' => $this->lifecycle_cost !== null ? (float) $this->lifecycle_cost : null,
            'payment_status' => $this->enum($this->payment_status),

            'signed_date' => $this->signed_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'auto_renew' => $this->auto_renew,
            'renewal_term_months' => $this->renewal_term_months,
            'notice_period_days' => $this->notice_period_days,

            'status' => $this->enum($this->status),
            'health_score' => $this->health_score,
            'days_until_expiry' => $daysUntilExpiry,
            'is_expired' => $daysUntilExpiry !== null && $daysUntilExpiry < 0,

            'attachments' => $this->whenLoaded('attachments', fn () => ContractAttachmentResource::collection($this->attachments)->resolve()),
            'renewals' => $this->whenLoaded('renewals', fn () => $this->renewals->map(fn ($r) => [
                'id' => $r->id,
                'previous_expiry' => $r->previous_expiry?->toDateString(),
                'new_expiry' => $r->new_expiry?->toDateString(),
                'previous_cost' => $r->previous_cost !== null ? (float) $r->previous_cost : null,
                'new_cost' => $r->new_cost !== null ? (float) $r->new_cost : null,
                'note' => $r->note,
                'created_at' => $r->created_at?->toIso8601String(),
            ])),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($a) => [
                'id' => $a->id,
                'event' => $a->event,
                'description' => $a->description,
                'meta' => $a->meta,
                'created_at' => $a->created_at?->toIso8601String(),
            ])),

            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
