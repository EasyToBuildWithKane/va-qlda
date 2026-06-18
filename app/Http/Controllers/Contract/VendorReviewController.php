<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreVendorReviewRequest;
use App\Http\Requests\Contract\UpdateVendorReviewRequest;
use App\Models\Contract;
use App\Models\SystemAccount;
use App\Models\Vendor;
use App\Models\VendorReview;
use App\Support\ContractActivityLogger;
use App\Support\NotificationDispatcher;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;

class VendorReviewController extends Controller
{
    /** Tạo đánh giá nhà cung cấp trên 6 tiêu chí (1–10) → tự tính tổng điểm. */
    public function store(StoreVendorReviewRequest $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validated();

        $review = $vendor->reviews()->create($this->reviewPayload($data, $request));
        $this->logReviewEvent($review, 'created', $request->user());

        return back()->with('success', 'Đã lưu đánh giá nhà cung cấp.');
    }

    public function update(UpdateVendorReviewRequest $request, Vendor $vendor, VendorReview $review): RedirectResponse
    {
        abort_unless($review->vendor_id === $vendor->id, 404);

        $data = $request->validated();
        $review->update($this->reviewPayload($data, $request, $review));
        $review->refresh();
        $this->logReviewEvent($review, 'updated', $request->user());

        return back()->with('success', 'Đã cập nhật đánh giá.');
    }

    public function destroy(Vendor $vendor, VendorReview $review): RedirectResponse
    {
        $this->authorize('update', $vendor);
        abort_unless($review->vendor_id === $vendor->id, 404);

        $this->logReviewEvent($review, 'deleted', request()->user());
        $review->delete();

        return back()->with('success', 'Đã xoá đánh giá.');
    }

    private function logReviewEvent(VendorReview $review, string $action, ?SystemAccount $account): void
    {
        $meta = [
            'review_id' => $review->id,
            'vendor_id' => $review->vendor_id,
            'total_score' => $review->total_score !== null ? (float) $review->total_score : null,
            'reviewed_at' => $review->reviewed_at?->toDateString(),
        ];

        $verb = match ($action) {
            'created' => 'tạo',
            'updated' => 'cập nhật',
            'deleted' => 'xoá',
            default => $action,
        };

        $score = $review->total_score !== null ? (float) $review->total_score : null;
        $vendor = Vendor::query()->find($review->vendor_id);

        if ($review->contract_id) {
            $contract = Contract::query()->find($review->contract_id);
            if ($contract) {
                ContractActivityLogger::vendorReview($contract, $action, $account, $meta);
            }
            if ($vendor && $contract) {
                NotificationDispatcher::vendorReview($vendor, $verb, $account, $score, $contract);
            }

            return;
        }

        $auditAction = match ($action) {
            'created' => 'review_created',
            'updated' => 'review_updated',
            'deleted' => 'review_deleted',
            default => 'review_'.$action,
        };

        if ($account) {
            SecurityAuditLogger::vendor($account, $auditAction, $review->vendor_id, $meta);
        }

        if ($vendor) {
            NotificationDispatcher::vendorReview($vendor, $verb, $account, $score, null);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reviewPayload(array $data, StoreVendorReviewRequest|UpdateVendorReviewRequest $request, ?VendorReview $existing = null): array
    {
        $scores = array_filter(
            array_map(fn (string $key) => $data[$key] ?? null, VendorReview::CRITERIA),
            fn ($v) => $v !== null && $v !== '',
        );
        $total = $scores === [] ? null : round(array_sum($scores) / count($scores), 2);

        $reviewerId = array_key_exists('reviewer_id', $data)
            ? $data['reviewer_id']
            : ($existing?->reviewer_id ?? $request->user()?->employee_id);

        return [
            ...$data,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => $data['reviewed_at'] ?? ($existing?->reviewed_at?->toDateString() ?? now()->toDateString()),
            'total_score' => $total,
        ];
    }
}
