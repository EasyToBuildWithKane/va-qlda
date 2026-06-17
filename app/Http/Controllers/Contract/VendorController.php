<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\ImportVendorRequest;
use App\Http\Requests\Contract\StoreVendorRequest;
use App\Http\Requests\Contract\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Contract;
use App\Models\Vendor;
use App\Models\VendorImportLog;
use App\Support\Enums\ContractReviewRecommendation;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $active = $request->query('active');
        if ($active === '1') {
            $query->where('is_active', true);
        } elseif ($active === '0') {
            $query->where('is_active', false);
        }

        $reviewed = $request->query('reviewed');
        if ($reviewed === 'yes') {
            $query->whereHas('latestReview', fn ($q) => $q->whereNotNull('total_score'));
        } elseif ($reviewed === 'no') {
            $query->whereDoesntHave('latestReview');
        }

        $vendors = VendorResource::collection($query->get())->resolve();

        return Inertia::render('Contract/Vendors', [
            'vendors' => [
                'data' => $vendors['data'] ?? (is_array($vendors) ? array_values($vendors) : []),
            ],
            'filters' => (object) $request->only(['q', 'scope', 'active', 'reviewed']),
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

    public function show(Request $request, Vendor $vendor): Response
    {
        $this->authorize('view', $vendor);

        $vendor->loadCount('contracts', 'reviews')
            ->loadSum('contracts as contracts_sum_annual_cost', 'annual_cost')
            ->load([
                'latestReview.reviewer',
                'reviews' => fn ($q) => $q->with('reviewer')->orderByDesc('reviewed_at')->limit(20),
                'contracts' => fn ($q) => $q->with('owner')->withCount('attachments')->orderByDesc('expiry_date'),
            ]);

        return Inertia::render('Contract/VendorShow', [
            'vendor' => VendorResource::make($vendor)->resolve(),
            'options' => [
                'recommendation' => ContractReviewRecommendation::options(),
                'criteria' => self::CRITERIA_LABELS,
                'employees' => Options::employees()->values()->all(),
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

    public function import(ImportVendorRequest $request): RedirectResponse
    {
        $account = $request->user();
        $validated = $request->validated();
        $rows = $validated['rows'];
        $overwrite = (bool) ($validated['overwrite'] ?? false);
        $count = 0;
        $overwrittenCount = 0;

        DB::transaction(function () use ($rows, $overwrite, $account, $request, &$count, &$overwrittenCount) {
            foreach ($rows as $row) {
                $payload = [
                    'name' => $row['name'],
                    'tax_code' => $row['tax_code'] ?? null,
                    'contact_name' => $row['contact_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'website' => $row['website'] ?? null,
                    'address' => $row['address'] ?? null,
                    'rating' => $row['rating'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                ];

                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                if ($code !== '') {
                    $payload['code'] = $code;
                }

                if ($overwrite) {
                    $existing = $this->findVendorForImportOverwrite($row);
                    if ($existing) {
                        if ($code === '') {
                            unset($payload['code']);
                        }
                        $existing->update($payload);
                        $overwrittenCount++;

                        continue;
                    }
                }

                if ($code !== '' && ! $overwrite && Vendor::query()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages([
                        'rows' => "Mã NCC «{$code}» đã tồn tại ({$row['name']}).",
                    ]);
                }

                Vendor::create($payload);
                $count++;
            }

            VendorImportLog::create([
                'account_id' => $account->id,
                'imported_count' => $count,
                'overwritten_count' => $overwrittenCount,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        $message = "Đã nhập {$count} nhà cung cấp";
        if ($overwrittenCount > 0) {
            $message .= ", ghi đè {$overwrittenCount} bản ghi";
        }

        return back()->with('success', "{$message}.");
    }

    public function exportData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vendor::class);

        $query = $this->filteredVendorQuery($request);

        $vendors = $query->limit(2000)->get()->map(fn (Vendor $v) => [
            'id' => $v->id,
            'code' => $v->code,
            'name' => $v->name,
            'tax_code' => $v->tax_code,
            'contact_name' => $v->contact_name,
            'email' => $v->email,
            'phone' => $v->phone,
            'website' => $v->website,
            'address' => $v->address,
            'rating' => $v->rating,
            'notes' => $v->notes,
            'is_active' => $v->is_active,
            'contracts_count' => $v->contracts_count ?? 0,
            'total_annual_cost' => $v->contracts_sum_annual_cost ?? 0,
            'review_score' => $v->latestReview?->total_score,
        ]);

        return response()->json(['data' => $vendors]);
    }

    public function importLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vendor::class);

        $logs = VendorImportLog::with('account:id,display_name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (VendorImportLog $log) => [
                'id' => $log->id,
                'user' => $log->account?->display_name ?? 'Hệ thống',
                'imported_count' => $log->imported_count,
                'overwritten_count' => $log->overwritten_count,
                'created_at_human' => $log->created_at?->diffForHumans(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findVendorForImportOverwrite(array $row): ?Vendor
    {
        $code = isset($row['code']) ? trim((string) $row['code']) : '';
        if ($code !== '') {
            $byCode = Vendor::query()->where('code', $code)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        $tax = isset($row['tax_code']) ? trim((string) $row['tax_code']) : '';
        if ($tax !== '') {
            $byTax = Vendor::query()->where('tax_code', $tax)->first();
            if ($byTax) {
                return $byTax;
            }
        }

        $name = trim((string) ($row['name'] ?? ''));

        return $name !== ''
            ? Vendor::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Vendor>
     */
    private function filteredVendorQuery(Request $request)
    {
        $query = Vendor::query()
            ->withCount('contracts')
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

        $active = $request->query('active');
        if ($active === '1') {
            $query->where('is_active', true);
        } elseif ($active === '0') {
            $query->where('is_active', false);
        }

        $reviewed = $request->query('reviewed');
        if ($reviewed === 'yes') {
            $query->whereHas('latestReview', fn ($q) => $q->whereNotNull('total_score'));
        } elseif ($reviewed === 'no') {
            $query->whereDoesntHave('latestReview');
        }

        return $query;
    }
}
