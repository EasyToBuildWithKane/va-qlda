<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\ImportVendorRequest;
use App\Http\Requests\Contract\StoreVendorRequest;
use App\Http\Requests\Contract\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\Vendor;
use App\Models\VendorImportLog;
use App\Support\ContractLifecycle\ContractServiceGroups;
use App\Support\Enums\ContractReviewRecommendation;
use App\Support\Enums\VendorCooperationStatus;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $account = $request->user();

        ContractServiceGroups::sync();

        $query = Vendor::query()
            ->withCount('contracts')
            ->withCount('reviews')
            ->withSum('contracts as contracts_sum_annual_cost', 'annual_cost')
            ->with(['latestReview', 'serviceCategories'])
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

        $this->applyCooperationStatusFilter($query, $request->query('active'));

        $reviewed = $request->query('reviewed');
        if ($reviewed === 'yes') {
            $query->whereHas('latestReview', fn ($q) => $q->whereNotNull('total_score'));
        } elseif ($reviewed === 'no') {
            $query->whereDoesntHave('latestReview');
        }

        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('serviceCategories', fn ($q) => $q->where('contract_categories.id', $categoryId));
        }

        $vendors = VendorResource::collection($query->get())->resolve();

        return Inertia::render('Contract/Vendors', [
            'vendors' => [
                'data' => $vendors['data'] ?? (is_array($vendors) ? array_values($vendors) : []),
            ],
            'filters' => (object) $request->only(['q', 'scope', 'active', 'reviewed', 'category_id']),
            'summary' => $this->vendorSummary(),
            'options' => [
                'recommendation' => ContractReviewRecommendation::options(),
                'criteria' => self::CRITERIA_LABELS,
                'categories' => $this->serviceCategoryOptions(),
                'cooperation_status' => VendorCooperationStatus::options(),
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

        ContractServiceGroups::sync();

        $vendor->loadCount('contracts', 'reviews')
            ->loadSum('contracts as contracts_sum_annual_cost', 'annual_cost')
            ->load([
                'latestReview.reviewer',
                'serviceCategories',
                'reviews' => fn ($q) => $q->with(['reviewer', 'contract'])
                    ->orderByRaw('CASE WHEN contract_id IS NULL THEN 0 ELSE 1 END')
                    ->orderByRaw('CASE WHEN contract_id IS NULL THEN reviewed_at END ASC')
                    ->orderByRaw('CASE WHEN contract_id IS NOT NULL THEN reviewed_at END DESC')
                    ->limit(50),
                'contracts' => fn ($q) => $q->with('owner')->withCount('attachments')->orderByDesc('expiry_date'),
            ]);

        return Inertia::render('Contract/VendorShow', [
            'vendor' => VendorResource::make($vendor)->resolve(),
            'options' => [
                'recommendation' => ContractReviewRecommendation::options(),
                'criteria' => self::CRITERIA_LABELS,
                'employees' => Options::employees()->values()->all(),
                'categories' => $this->serviceCategoryOptions(),
                'cooperation_status' => VendorCooperationStatus::options(),
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
        $validated = $request->validated();
        $hasCategories = array_key_exists('category_ids', $validated)
            || array_key_exists('category_names', $validated);
        $categoryIds = $hasCategories
            ? $this->resolveServiceCategoryIds(
                $validated['category_ids'] ?? [],
                $validated['category_names'] ?? [],
            )
            : [];
        unset($validated['category_ids'], $validated['category_names']);

        $vendor = DB::transaction(function () use ($validated, $hasCategories, $categoryIds) {
            $vendor = Vendor::create($validated);
            if ($hasCategories) {
                $vendor->serviceCategories()->sync($categoryIds);
            }

            return $vendor;
        });

        return back()->with([
            'success' => 'Đã thêm nhà cung cấp.',
            'created_vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validated();
        $hasCategories = array_key_exists('category_ids', $validated)
            || array_key_exists('category_names', $validated);
        $categoryIds = $hasCategories
            ? $this->resolveServiceCategoryIds(
                $validated['category_ids'] ?? [],
                $validated['category_names'] ?? [],
            )
            : [];
        unset($validated['category_ids'], $validated['category_names']);

        DB::transaction(function () use ($vendor, $validated, $hasCategories, $categoryIds) {
            $vendor->update($validated);
            if ($hasCategories) {
                $vendor->serviceCategories()->sync($categoryIds);
            }
        });

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

        ContractServiceGroups::sync();

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
                ];

                $status = $this->resolveImportCooperationStatus($row);
                if ($status) {
                    $payload['cooperation_status'] = $status;
                }

                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                if ($code !== '') {
                    $payload['code'] = $code;
                }

                $categoryIds = $this->resolveImportCategoryIds($row['category_ids'] ?? $row['service_categories'] ?? null);

                if ($overwrite) {
                    $existing = $this->findVendorForImportOverwrite($row);
                    if ($existing) {
                        if ($code === '') {
                            unset($payload['code']);
                        }
                        $existing->update($payload);
                        if ($categoryIds !== null) {
                            $existing->serviceCategories()->sync($categoryIds);
                        }
                        $overwrittenCount++;

                        continue;
                    }
                }

                if ($code !== '' && ! $overwrite && Vendor::query()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages([
                        'rows' => "Mã NCC «{$code}» đã tồn tại ({$row['name']}).",
                    ]);
                }

                $vendor = Vendor::create($payload);
                if ($categoryIds !== null) {
                    $vendor->serviceCategories()->sync($categoryIds);
                }
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
            'cooperation_status' => $v->cooperation_status?->value
                ?? ($v->is_active ? VendorCooperationStatus::Active->value : VendorCooperationStatus::Inactive->value),
            'service_categories' => $v->serviceCategories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values()->all(),
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
     * @param  \Illuminate\Database\Eloquent\Builder<Vendor>  $query
     */
    private function applyCooperationStatusFilter($query, mixed $active): void
    {
        $raw = is_string($active) ? trim($active) : '';
        if ($raw === '') {
            return;
        }

        // Tương thích filter cũ active=1|0.
        if ($raw === '1') {
            $query->where('cooperation_status', VendorCooperationStatus::Active->value);

            return;
        }
        if ($raw === '0') {
            $query->where('cooperation_status', VendorCooperationStatus::Inactive->value);

            return;
        }

        if (in_array($raw, VendorCooperationStatus::values(), true)) {
            $query->where('cooperation_status', $raw);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveImportCooperationStatus(array $row): ?VendorCooperationStatus
    {
        if (! empty($row['cooperation_status']) && is_string($row['cooperation_status'])) {
            return VendorCooperationStatus::tryFrom($row['cooperation_status'])
                ?? VendorCooperationStatus::tryFromLabel($row['cooperation_status']);
        }

        if (array_key_exists('is_active', $row)) {
            return (bool) $row['is_active']
                ? VendorCooperationStatus::Active
                : VendorCooperationStatus::Inactive;
        }

        return VendorCooperationStatus::Active;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function serviceCategoryOptions(): array
    {
        return ContractCategory::query()
            ->whereNull('vendor_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ContractCategory $c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();
    }

    /**
     * Gộp id đã chọn + tên loại dịch vụ mới (tạo danh mục chung nếu chưa có).
     *
     * @param  list<int|string>  $ids
     * @param  list<string>  $names
     * @return list<int>
     */
    private function resolveServiceCategoryIds(array $ids, array $names): array
    {
        $resolved = [];

        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $resolved[] = $n;
            }
        }

        $maxSort = (int) ContractCategory::query()->whereNull('vendor_id')->max('sort_order');

        foreach ($names as $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }

            $cat = ContractCategory::query()
                ->whereNull('vendor_id')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if (! $cat) {
                $maxSort++;
                $cat = ContractCategory::create([
                    'name' => $name,
                    'vendor_id' => null,
                    'slug' => Str::slug($name) ?: 'nhom-dv-'.$maxSort,
                    'sort_order' => $maxSort,
                ]);
            }

            $resolved[] = $cat->id;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return list<int>|null null = không đụng pivot khi import; [] = xoá hết gắn kết
     */
    private function resolveImportCategoryIds(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return null;
            }
            $parts = preg_split('/[;|,]/', $trimmed) ?: [];
            $raw = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
        }

        if (! is_array($raw)) {
            return null;
        }

        if ($raw === []) {
            return null;
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;

                continue;
            }
            $name = is_array($item) ? trim((string) ($item['name'] ?? '')) : trim((string) $item);
            if ($name === '') {
                continue;
            }
            $cat = ContractCategory::query()
                ->whereNull('vendor_id')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if ($cat) {
                $ids[] = $cat->id;
            }
        }

        return array_values(array_unique($ids));
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
            ->with(['latestReview', 'serviceCategories'])
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

        $this->applyCooperationStatusFilter($query, $request->query('active'));

        $reviewed = $request->query('reviewed');
        if ($reviewed === 'yes') {
            $query->whereHas('latestReview', fn ($q) => $q->whereNotNull('total_score'));
        } elseif ($reviewed === 'no') {
            $query->whereDoesntHave('latestReview');
        }

        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('serviceCategories', fn ($q) => $q->where('contract_categories.id', $categoryId));
        }

        return $query;
    }
}
