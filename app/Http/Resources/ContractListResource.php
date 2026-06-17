<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bản rút gọn cho Explorer / danh sách — không tải quan hệ nặng.
 *
 * @mixin \App\Models\Contract
 */
class ContractListResource extends JsonResource
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
            'root_contract_id' => $this->root_contract_id,
            'using_unit' => $this->using_unit,
            'signed_date' => $this->signed_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'annual_cost' => $this->annual_cost !== null ? (float) $this->annual_cost : null,
            'annual_cost_resolved' => $this->annualCostResolved(),
            'monthly_cost' => $this->monthly_cost !== null ? (float) $this->monthly_cost : null,
            'lifecycle_cost' => $this->lifecycle_cost !== null ? (float) $this->lifecycle_cost : null,
            'currency' => $this->currency,
            'billing_cycle' => $this->enum($this->billing_cycle),
            'status' => $this->enum($this->status),
            'payment_status' => $this->enum($this->payment_status),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $daysUntilExpiry,
            'is_expired' => $daysUntilExpiry !== null && $daysUntilExpiry < 0,
            'attachments_count' => $this->whenCounted('attachments'),
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
