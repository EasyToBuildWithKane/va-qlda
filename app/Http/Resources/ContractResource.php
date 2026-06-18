<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Models\VendorReview;
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
                'latest_review' => $this->vendor->relationLoaded('latestReview') && $this->vendor->latestReview
                    ? $this->vendorReviewArray($this->vendor->latestReview)
                    : null,
            ] : null),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'root_contract_id' => $this->root_contract_id,
            'department_id' => $this->department_id,
            'using_unit' => $this->using_unit,
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),

            'currency' => $this->currency,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'unit_price_resolved' => $this->unitPriceResolved(),
            'monthly_cost' => $this->monthly_cost !== null ? (float) $this->monthly_cost : null,
            'monthly_cost_resolved' => $this->monthlyCostResolved(),
            'annual_cost' => $this->annual_cost !== null ? (float) $this->annual_cost : null,
            'annual_cost_resolved' => $this->annualCostResolved(),
            'lifecycle_cost' => $this->lifecycle_cost !== null ? (float) $this->lifecycle_cost : null,
            'lifecycle_cost_resolved' => $this->lifecycleCostResolved(),
            'payment_status' => $this->enum($this->payment_status),
            'billing_cycle' => $this->enum($this->billing_cycle),

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

            'finances' => $this->whenLoaded('finances', fn () => $this->finances->map(fn ($f) => $this->financeRow($f))),

            'finance_targets' => $this->when(
                $this->root_contract_id === null,
                fn () => $this->financeTargetsArray(),
            ),
            'chain_finances' => $this->when(
                $this->root_contract_id === null,
                fn () => $this->chainFinancesArray(),
            ),

            'attachments' => $this->whenLoaded('attachments', fn () => ContractAttachmentResource::collection($this->attachments)->resolve()),

            'addenda' => $this->whenLoaded('addenda', fn () => $this->addenda->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'status' => $this->enum($a->status),
                'effective_date' => $a->effective_date?->toDateString(),
                'expiry_date' => $a->expiry_date?->toDateString(),
                'annual_cost' => $a->annual_cost !== null ? (float) $a->annual_cost : null,
                'currency' => $a->currency,
                'attachments_count' => $a->relationLoaded('attachments') ? $a->attachments->count() : 0,
                'created_at' => $a->created_at?->toIso8601String(),
            ])),

            'reviews_for_contract' => VendorReview::where('contract_id', $this->id)
                ->with('reviewer')
                ->latest('reviewed_at')
                ->get()
                ->map(fn ($r) => $this->vendorReviewArray($r)),

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

    /**
     * @param  \App\Models\ContractFinance  $f
     * @return array<string, mixed>
     */
    private function financeRow($f): array
    {
        return [
            'id' => $f->id,
            'used_date' => $f->used_date?->toDateString(),
            'quantity' => $f->quantity !== null ? (float) $f->quantity : null,
            'unit_price' => $f->unit_price !== null ? (float) $f->unit_price : null,
            'init_fee' => $f->init_fee !== null ? (float) $f->init_fee : null,
            'maintenance_fee' => $f->maintenance_fee !== null ? (float) $f->maintenance_fee : null,
            'term_months' => $f->term_months,
            'total' => $f->total !== null ? (float) $f->total : null,
            'renewal_cost' => $f->renewal_cost !== null ? (float) $f->renewal_cost : null,
            'note' => $f->note,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function financeTargetsArray(): array
    {
        $targets = [[
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => 'root',
            'label' => 'Hợp đồng gốc',
        ]];

        if ($this->relationLoaded('addenda')) {
            foreach ($this->addenda->sortBy('effective_date') as $addendum) {
                $targets[] = [
                    'id' => $addendum->id,
                    'code' => $addendum->code,
                    'name' => $addendum->name,
                    'kind' => 'addendum',
                    'label' => 'Phụ lục / gia hạn',
                ];
            }
        }

        return $targets;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function chainFinancesArray(): array
    {
        $rows = [];

        if ($this->relationLoaded('finances')) {
            foreach ($this->finances as $f) {
                $rows[] = array_merge($this->financeRow($f), [
                    'contract_id' => $this->id,
                    'contract_code' => $this->code,
                    'contract_kind' => 'root',
                    'contract_label' => 'Gốc',
                ]);
            }
        }

        if ($this->relationLoaded('addenda')) {
            foreach ($this->addenda as $addendum) {
                if (! $addendum->relationLoaded('finances')) {
                    continue;
                }
                foreach ($addendum->finances as $f) {
                    $rows[] = array_merge($this->financeRow($f), [
                        'contract_id' => $addendum->id,
                        'contract_code' => $addendum->code,
                        'contract_kind' => 'addendum',
                        'contract_label' => 'Phụ lục',
                    ]);
                }
            }
        }

        usort($rows, function (array $a, array $b) {
            return strcmp((string) ($b['used_date'] ?? ''), (string) ($a['used_date'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param  \App\Models\VendorReview  $r
     * @return array<string, mixed>
     */
    private function vendorReviewArray($r): array
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
            'recommendation' => $this->enum($r->recommendation),
            'note' => $r->note,
        ];
    }
}
