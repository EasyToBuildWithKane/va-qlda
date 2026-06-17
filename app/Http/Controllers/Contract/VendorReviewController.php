<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreVendorReviewRequest;
use App\Http\Requests\Contract\UpdateVendorReviewRequest;
use App\Models\Vendor;
use App\Models\VendorReview;
use Illuminate\Http\RedirectResponse;

class VendorReviewController extends Controller
{
    /** Tạo đánh giá nhà cung cấp trên 6 tiêu chí (0–10) → tự tính tổng điểm. */
    public function store(StoreVendorReviewRequest $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validated();

        $vendor->reviews()->create($this->reviewPayload($data, $request));

        return back()->with('success', 'Đã lưu đánh giá nhà cung cấp.');
    }

    public function update(UpdateVendorReviewRequest $request, Vendor $vendor, VendorReview $review): RedirectResponse
    {
        abort_unless($review->vendor_id === $vendor->id, 404);

        $data = $request->validated();
        $review->update($this->reviewPayload($data, $request, $review));

        return back()->with('success', 'Đã cập nhật đánh giá.');
    }

    public function destroy(Vendor $vendor, VendorReview $review): RedirectResponse
    {
        $this->authorize('update', $vendor);
        abort_unless($review->vendor_id === $vendor->id, 404);

        $review->delete();

        return back()->with('success', 'Đã xoá đánh giá.');
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
