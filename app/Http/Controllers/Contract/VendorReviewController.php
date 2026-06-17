<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreVendorReviewRequest;
use App\Models\Vendor;
use App\Models\VendorReview;
use Illuminate\Http\RedirectResponse;

class VendorReviewController extends Controller
{
    /** Tạo đánh giá nhà cung cấp trên 6 tiêu chí (0–10) → tự tính tổng điểm. */
    public function store(StoreVendorReviewRequest $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validated();

        $scores = array_filter(
            array_map(fn (string $key) => $data[$key] ?? null, VendorReview::CRITERIA),
            fn ($v) => $v !== null && $v !== '',
        );
        $total = $scores === [] ? null : round(array_sum($scores) / count($scores), 2);

        $vendor->reviews()->create([
            ...$data,
            'reviewer_id' => $data['reviewer_id'] ?? $request->user()?->employee_id,
            'reviewed_at' => $data['reviewed_at'] ?? now()->toDateString(),
            'total_score' => $total,
        ]);

        return back()->with('success', 'Đã lưu đánh giá nhà cung cấp.');
    }
}
