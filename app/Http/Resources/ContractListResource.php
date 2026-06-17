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
        $daysUntilExpiry = $this->daysUntilExpiry();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'vendor_id' => $this->vendor_id,
            'category_id' => $this->category_id,
            'root_contract_id' => $this->root_contract_id,
            'using_unit' => $this->using_unit,
            'signed_date' => $this->signed_date?->toDateString(),
            'annual_cost' => $this->annual_cost !== null ? (float) $this->annual_cost : null,
            'annual_cost_resolved' => $this->annualCostResolved(),
            'lifecycle_cost' => $this->lifecycle_cost !== null ? (float) $this->lifecycle_cost : null,
            'currency' => $this->currency,
            'status' => $this->enum($this->status),
            'payment_status' => $this->enum($this->payment_status),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $daysUntilExpiry,
            'is_expired' => $daysUntilExpiry !== null && $daysUntilExpiry < 0,
            'attachments_count' => $this->whenCounted('attachments'),
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
        ];
    }
}
