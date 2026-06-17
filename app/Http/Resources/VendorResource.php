<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Models\Vendor;
use App\Models\VendorReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Vendor
 */
class VendorResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        /** @var VendorReview|null $latest */
        $latest = $this->relationLoaded('latestReview') ? $this->latestReview : null;
        $latestScore = $latest && $latest->total_score !== null ? (float) $latest->total_score : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tax_code' => $this->tax_code,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'rating' => $this->rating,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'contracts_count' => $this->whenCounted('contracts'),
            'total_annual_cost' => $this->when(
                isset($this->contracts_sum_annual_cost),
                fn () => (float) $this->contracts_sum_annual_cost,
            ),
            'review_score' => $latestScore,
            'is_low_score' => $latestScore !== null && $latestScore < 7,
            'reviews_count' => $this->whenCounted('reviews'),
            'latest_review' => $latest ? $this->reviewArray($latest) : null,
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn (VendorReview $r) => $this->reviewArray($r))),
            'contracts' => ContractListResource::collection($this->whenLoaded('contracts')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'evaluate' => $user->can('create', Vendor::class),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewArray(VendorReview $r): array
    {
        return [
            'id' => $r->id,
            'reviewed_at' => $r->reviewed_at?->toDateString(),
            'reviewer' => $r->relationLoaded('reviewer') ? $this->person($r->reviewer) : null,
            'service_quality' => $r->service_quality !== null ? (float) $r->service_quality : null,
            'sla' => $r->sla !== null ? (float) $r->sla : null,
            'speed' => $r->speed !== null ? (float) $r->speed : null,
            'price_satisfaction' => $r->price_satisfaction !== null ? (float) $r->price_satisfaction : null,
            'stability' => $r->stability !== null ? (float) $r->stability : null,
            'attitude' => $r->attitude !== null ? (float) $r->attitude : null,
            'total_score' => $r->total_score !== null ? (float) $r->total_score : null,
            'recommendation' => $r->recommendation ? [
                'value' => $r->recommendation->value,
                'label' => $r->recommendation->label(),
                'color' => $r->recommendation->color(),
            ] : null,
            'note' => $r->note,
        ];
    }
}
