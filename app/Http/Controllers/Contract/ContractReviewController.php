<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractReviewRequest;
use App\Models\Contract;
use App\Models\VendorReview;
use Illuminate\Http\RedirectResponse;

class ContractReviewController extends Controller
{
    /**
     * Tạo đánh giá cho hợp đồng (gắn vào vendor của hợp đồng + lưu contract_id).
     * Vendor page tổng hợp điểm trung bình từ tất cả đánh giá (bao gồm cả đánh giá qua contract).
     */
    public function store(StoreContractReviewRequest $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        abort_unless($contract->vendor_id !== null, 422, 'Hợp đồng chưa gắn nhà cung cấp.');

        $data = $request->validated();

        $scores = array_filter(
            array_map(fn (string $key) => $data[$key] ?? null, VendorReview::CRITERIA),
            fn ($v) => $v !== null && $v !== '',
        );
        $total = $scores === [] ? null : round(array_sum($scores) / count($scores), 2);

        VendorReview::create([
            ...$data,
            'vendor_id' => $contract->vendor_id,
            'contract_id' => $contract->id,
            'reviewer_id' => $data['reviewer_id'] ?? $request->user()?->employee_id,
            'reviewed_at' => $data['reviewed_at'] ?? now()->toDateString(),
            'total_score' => $total,
        ]);

        return back()->with('success', 'Đã lưu đánh giá hợp đồng.');
    }

    public function destroy(Contract $contract, VendorReview $review): RedirectResponse
    {
        $this->authorize('update', $contract);
        abort_unless($review->contract_id === $contract->id, 404);

        $review->delete();

        return back()->with('success', 'Đã xoá đánh giá.');
    }
}
