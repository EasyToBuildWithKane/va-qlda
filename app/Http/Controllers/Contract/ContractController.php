<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\ImportContractRequest;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Http\Resources\ContractListResource;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\Vendor;
use App\Services\NotificationService;
use App\Support\ContractActivityLogger;
use App\Support\ContractLifecycle\ContractRenewalCalculator;
use App\Support\Enums\ContractAttachmentCategory;
use App\Support\Enums\ContractBillingCycle;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\NotificationType;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function __construct(private readonly ContractRenewalCalculator $calculator) {}

    /** Chuẩn hoá giá trị so sánh / lưu lịch sử (enum, ngày, scalar). */
    private function trackValueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * Explorer — dữ liệu phẳng (vendors, categories, contracts); cây NCC →
     * nhóm dịch vụ → hợp đồng dựng phía client (useContractExplorer).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $account = $request->user();

        $query = Contract::query()->with('owner');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }
        if ($vendorId = $request->query('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('using_unit', 'like', "%{$search}%"));
        }

        $contracts = $query->orderBy('name')->get();

        return Inertia::render('Contract/Index', [
            'contracts' => ContractListResource::collection($contracts),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'code', 'name'])->values()->all(),
            'categories' => ContractCategory::query()->orderBy('sort_order')->get(['id', 'vendor_id', 'name'])->values()->all(),
            'filters' => (object) $request->only(['status', 'payment_status', 'vendor_id', 'category_id', 'q']),
            'summary' => $this->portfolioSummary(),
            'options' => [
                'status' => ContractStatus::options(),
                'paymentStatus' => ContractPaymentStatus::options(),
                'billingCycle' => ContractBillingCycle::options(),
                'employees' => Options::employees()->values()->all(),
                'departments' => Options::departments()->values()->all(),
            ],
            'can' => [
                'create' => $account->can('create', Contract::class),
                'import' => $account->can('import', Contract::class),
            ],
        ]);
    }

    /**
     * Tổng hợp toàn cục cho dải KPI Explorer (không phụ thuộc bộ lọc hiện tại)
     * — tổng hợp đồng, đang hiệu lực, sắp hết hạn, đã hết hạn, chưa thanh toán
     * và tổng chi phí năm. Các thẻ này lọc nhanh theo `status`/`payment_status`.
     *
     * @return array<string, int|float>
     */
    private function portfolioSummary(): array
    {
        /** @var Collection<int, Contract> $all */
        $all = Contract::query()->get(['status', 'payment_status', 'expiry_date', 'annual_cost']);
        $today = Carbon::today();
        $window = $this->calculator->expiringWindowDays();

        $daysOf = fn (Contract $c): ?int => $c->expiry_date
            ? $today->diffInDays($c->expiry_date, false)
            : null;

        return [
            'total' => $all->count(),
            'active' => $all->filter(fn (Contract $c) => $c->status->isLive())->count(),
            'expiring_soon' => $all->filter(function (Contract $c) use ($daysOf, $window) {
                $d = $daysOf($c);

                return $d !== null && $d >= 0 && $d <= $window && $c->status->isLive();
            })->count(),
            'expired' => $all->filter(function (Contract $c) use ($daysOf) {
                if ($c->status === ContractStatus::Terminated) {
                    return false;
                }
                $d = $daysOf($c);

                return $c->status === ContractStatus::Expired || ($d !== null && $d < 0);
            })->count(),
            'unpaid' => $all->filter(
                fn (Contract $c) => $c->payment_status === ContractPaymentStatus::Unpaid && $c->status->isLive(),
            )->count(),
            'annual_cost' => round((float) $all->sum(fn (Contract $c) => (float) ($c->annual_cost ?? 0)), 2),
        ];
    }

    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load([
            'vendor',
            'category',
            'owner',
            'manager',
            'attachments' => fn ($a) => $a->with('uploadedBy'),
            'renewals',
            'finances',
            'reviews' => fn ($r) => $r->with('reviewer'),
            'activities' => fn ($a) => $a->limit(50),
        ]);

        return Inertia::render('Contract/Show', [
            'contract' => new ContractResource($contract),
            'options' => [
                'status' => ContractStatus::options(),
                'paymentStatus' => ContractPaymentStatus::options(),
                'billingCycle' => ContractBillingCycle::options(),
                'employees' => Options::employees(),
                'departments' => Options::departments(),
                'vendors' => Vendor::query()->orderBy('name')->get(['id', 'code', 'name']),
                'categories' => ContractCategory::query()->orderBy('sort_order')->get(['id', 'vendor_id', 'name']),
                'attachmentCategories' => ContractAttachmentCategory::options(),
            ],
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $contract = Contract::create([
            ...$data,
            'status' => $data['status'] ?? ContractStatus::Draft->value,
            'payment_status' => $data['payment_status'] ?? ContractPaymentStatus::Unpaid->value,
        ]);

        ContractActivityLogger::created($contract, $request->user());

        return back()->with([
            'success' => 'Đã tạo hợp đồng.',
            'created_contract_id' => $contract->id,
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $data = $request->validated();

        $trackFields = array_keys($data);
        $before = $contract->only($trackFields);

        $contract->update($data);
        $contract->refresh();

        $changes = [];
        foreach ($trackFields as $field) {
            $newVal = $contract->{$field};
            $oldVal = $before[$field] ?? null;
            if ($this->trackValueToString($newVal) !== $this->trackValueToString($oldVal)) {
                $changes[$field] = match (true) {
                    $newVal instanceof \BackedEnum => $newVal->value,
                    $newVal instanceof \DateTimeInterface => $newVal->format('Y-m-d'),
                    default => $newVal,
                };
            }
        }
        ContractActivityLogger::updated($contract, $request->user(), $changes);

        return back()->with('success', 'Đã cập nhật hợp đồng.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        ContractActivityLogger::deleted($contract, request()->user());
        $contract->delete();

        return redirect()->route('contracts.index')->with('success', 'Đã xoá hợp đồng.');
    }

    public function import(ImportContractRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();
        $stats = ['created' => 0, 'updated' => 0, 'finances' => 0, 'reviews' => 0];

        DB::transaction(function () use ($data, $account, &$stats) {
            /** @var array<string, Contract> $byCode hợp đồng đã nhập, key = Mã HĐ chuẩn hoá */
            $byCode = [];

            foreach ($data['rows'] as $row) {
                $vendorId = $row['vendor_id'] ?? $this->resolveVendorId($row['vendor_name'] ?? null);
                $categoryId = $row['category_id'] ?? $this->resolveCategoryId($row['category_name'] ?? null, $vendorId);

                $attrs = [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'vendor_id' => $vendorId,
                    'category_id' => $categoryId,
                    'department_id' => $row['department_id'] ?? null,
                    'using_unit' => $row['using_unit'] ?? null,
                    'owner_id' => $row['owner_id'] ?? null,
                    'manager_id' => $row['manager_id'] ?? null,
                    'annual_cost' => $row['annual_cost'] ?? null,
                    'lifecycle_cost' => $row['lifecycle_cost'] ?? null,
                    'payment_status' => $row['payment_status'] ?? ContractPaymentStatus::Unpaid->value,
                    'billing_cycle' => $row['billing_cycle'] ?? null,
                    'effective_date' => $row['effective_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'status' => $row['status'] ?? ContractStatus::Active->value,
                ];

                $code = trim((string) ($row['code'] ?? ''));
                $existing = $code !== '' ? Contract::withTrashed()->where('code', $code)->first() : null;

                if ($existing) {
                    $existing->fill($attrs)->save();
                    $contract = $existing;
                    $stats['updated']++;
                } else {
                    $contract = Contract::create($code !== '' ? ['code' => $code, ...$attrs] : $attrs);
                    ContractActivityLogger::created($contract, $account);
                    $stats['created']++;
                }

                $byCode[$this->normCode($contract->code)] = $contract;

                foreach ($row['links'] ?? [] as $link) {
                    $link = trim((string) $link);
                    if ($link === '') {
                        continue;
                    }
                    $contract->attachments()->create([
                        'category' => 'contract',
                        'uploaded_by_id' => $account?->employee_id,
                        'original_name' => Str::limit(basename(str_replace('\\', '/', $link)), 200, ''),
                        'external_url' => $link,
                    ]);
                }
            }

            $resolveContract = function (string $code) use (&$byCode): ?Contract {
                $key = $this->normCode($code);
                if (! array_key_exists($key, $byCode)) {
                    $byCode[$key] = Contract::withTrashed()->where('code', $code)->first();
                }

                return $byCode[$key];
            };

            foreach ($data['finances'] ?? [] as $f) {
                $contract = $resolveContract($f['code']);
                if (! $contract) {
                    continue;
                }
                $contract->finances()->create([
                    'used_date' => $f['used_date'] ?? null,
                    'quantity' => $f['quantity'] ?? null,
                    'unit_price' => $f['unit_price'] ?? null,
                    'init_fee' => $f['init_fee'] ?? null,
                    'maintenance_fee' => $f['maintenance_fee'] ?? null,
                    'term_months' => $f['term_months'] ?? null,
                    'total' => $f['total'] ?? null,
                    'renewal_cost' => $f['renewal_cost'] ?? null,
                ]);
                $stats['finances']++;
            }

            foreach ($data['reviews'] ?? [] as $rv) {
                $contract = $resolveContract($rv['code']);
                if (! $contract) {
                    continue;
                }
                $contract->reviews()->create([
                    'reviewer_id' => $this->resolveEmployeeIdByEmail($rv['reviewer_email'] ?? null),
                    'reviewed_at' => $rv['reviewed_at'] ?? null,
                    'service_quality' => $rv['service_quality'] ?? null,
                    'sla' => $rv['sla'] ?? null,
                    'speed' => $rv['speed'] ?? null,
                    'price_satisfaction' => $rv['price_satisfaction'] ?? null,
                    'stability' => $rv['stability'] ?? null,
                    'attitude' => $rv['attitude'] ?? null,
                    'total_score' => $rv['total_score'] ?? null,
                    'recommendation' => $rv['recommendation'] ?? null,
                ]);
                $stats['reviews']++;
            }
        });

        $summary = "Nhập {$stats['created']} mới, cập nhật {$stats['updated']} hợp đồng";
        if ($stats['finances'] || $stats['reviews']) {
            $summary .= " · {$stats['finances']} dòng chi phí · {$stats['reviews']} đánh giá";
        }

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            $summary.' từ Excel',
        );

        return back()->with('success', $summary.'.');
    }

    /** Tìm NCC theo tên (chuẩn hoá), tạo mới nếu chưa có — giúp nhập thẳng từ file thật. */
    private function resolveVendorId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $vendor = Vendor::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Vendor::create(['name' => $name]);

        return $vendor->id;
    }

    /** Tìm nhóm dịch vụ theo tên trong phạm vi NCC (hoặc dùng chung), tạo mới nếu cần. */
    private function resolveCategoryId(?string $name, ?int $vendorId): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $category = ContractCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where(fn ($q) => $q->where('vendor_id', $vendorId)->orWhereNull('vendor_id'))
            ->first()
            ?? ContractCategory::create(['name' => $name, 'vendor_id' => $vendorId]);

        return $category->id;
    }

    private function resolveEmployeeIdByEmail(?string $email): ?int
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        return \App\Models\Employee::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->value('id');
    }

    /** Chuẩn hoá Mã HĐ để khớp giữa các sheet (không phân biệt hoa/thường, khoảng trắng). */
    private function normCode(?string $code): string
    {
        return mb_strtolower(trim((string) $code));
    }

    /** JSON cho client dựng file Excel có style (useContractExport). */
    public function export(): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = Contract::query()
            ->with(['vendor', 'category', 'owner'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'contracts' => ContractResource::collection($contracts)->resolve(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
