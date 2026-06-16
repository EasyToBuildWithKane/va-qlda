<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Vendor
 */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

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
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
