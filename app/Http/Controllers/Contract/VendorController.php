<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreVendorRequest;
use App\Http\Requests\Contract\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Contract;
use App\Models\Vendor;
use App\Support\Enums\ContractReviewRecommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $account = $request->user();

        $query = Vendor::query()
            ->withCount('contracts')
            ->withCount('reviews')
            ->withSum('contracts as contracts_sum_annual_cost', 'annual_cost')
            ->with('latestReview')
            ->orderBy('name');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('tax_code', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%"));
        }

        $scope = $request->query('scope');
        if ($scope === 'with_contracts') {
            $query->whereHas('contracts');
        } elseif ($scope === 'low_score') {
            $query->whereHas('latestReview', fn ($q) => $q->where('total_score', '<', 7));
        }

        return Inertia::render('Contract/Vendors', [
            'vendors' => VendorResource::collection($query->get()),
            'filters' => (object) $request->only(['q', 'scope']),
            'summary' => $this->vendorSummary(),
            'options' => [
                'recommendation' => ContractReviewRecommendation::options(),
                'criteria' => self::CRITERIA_LABELS,
            ],
            'can' => [
                'create' => $account->can('create', Vendor::class),
                'evaluate' => $account->can('create', Vendor::class),
            ],
        ]);
    }

    /** Nhãn tiếng Việt cho 6 tiêu chí đánh giá NCC (khớp VendorReview::CRITERIA). */
    private const CRITERIA_LABELS = [
        ['key' => 'service_quality', 'label' => 'Chất lượng dịch vụ', 'hint' => 'Đáp ứng yêu cầu, đúng cam kết'],
        ['key' => 'sla', 'label' => 'Tuân thủ SLA', 'hint' => 'Cam kết mức dịch vụ, thời gian phản hồi'],
        ['key' => 'speed', 'label' => 'Tốc độ xử lý', 'hint' => 'Triển khai & hỗ trợ nhanh chóng'],
        ['key' => 'price_satisfaction', 'label' => 'Mức độ hài lòng về giá', 'hint' => 'Giá hợp lý so với giá trị nhận được'],
        ['key' => 'stability', 'label' => 'Độ ổn định', 'hint' => 'Dịch vụ ổn định, ít sự cố'],
        ['key' => 'attitude', 'label' => 'Thái độ hợp tác', 'hint' => 'Thiện chí, chuyên nghiệp'],
    ];

    /**
     * Tổng hợp toàn cục cho dải KPI nhà cung cấp (không phụ thuộc tìm kiếm):
     * tổng NCC, số NCC đang có hợp đồng, tổng hợp đồng, tổng chi phí năm.
     *
     * @return array<string, int|float>
     */
    private function vendorSummary(): array
    {
        $totalVendors = Vendor::query()->count();
        $withContracts = Vendor::query()->whereHas('contracts')->count();
        $totalContracts = Contract::query()->count();
        $annualCost = (float) Contract::query()->sum('annual_cost');

        // Điểm latest review mỗi NCC (chỉ NCC đã có đánh giá).
        $latestScores = Vendor::query()
            ->with('latestReview')
            ->get()
            ->map(fn (Vendor $v) => $v->latestReview?->total_score !== null ? (float) $v->latestReview->total_score : null)
            ->filter(fn (?float $s) => $s !== null)
            ->values();

        return [
            'total' => $totalVendors,
            'with_contracts' => $withContracts,
            'without_contracts' => max(0, $totalVendors - $withContracts),
            'contracts' => $totalContracts,
            'annual_cost' => round($annualCost, 2),
            'reviewed' => $latestScores->count(),
            'low_score' => $latestScores->filter(fn (float $s) => $s < 7)->count(),
            'avg_score' => $latestScores->isEmpty() ? null : round($latestScores->avg(), 2),
        ];
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::create($request->validated());

        return back()->with([
            'success' => 'Đã thêm nhà cung cấp.',
            'created_vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return back()->with('success', 'Đã cập nhật nhà cung cấp.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);

        if ($vendor->contracts()->exists()) {
            return back()->with('error', 'Không thể xoá: nhà cung cấp đang có hợp đồng.');
        }

        $vendor->delete();

        return back()->with('success', 'Đã xoá nhà cung cấp.');
    }
}
