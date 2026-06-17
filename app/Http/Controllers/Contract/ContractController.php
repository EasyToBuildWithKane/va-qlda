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
use App\Support\Enums\ContractAttachmentCategory;
use App\Support\Enums\ContractPaymentStatus;
use App\Support\Enums\ContractStatus;
use App\Support\Enums\NotificationType;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
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
        if ($vendorId = $request->query('vendor_id')) {
            $query->where('vendor_id', $vendorId);
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
            'filters' => (object) $request->only(['status', 'vendor_id', 'q']),
            'options' => [
                'status' => ContractStatus::options(),
                'paymentStatus' => ContractPaymentStatus::options(),
                'employees' => Options::employees()->values()->all(),
                'departments' => Options::departments()->values()->all(),
            ],
            'can' => [
                'create' => $account->can('create', Contract::class),
                'import' => $account->can('import', Contract::class),
            ],
        ]);
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
            'activities' => fn ($a) => $a->limit(50),
        ]);

        return Inertia::render('Contract/Show', [
            'contract' => new ContractResource($contract),
            'options' => [
                'status' => ContractStatus::options(),
                'paymentStatus' => ContractPaymentStatus::options(),
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
        $created = 0;

        DB::transaction(function () use ($data, $account, &$created) {
            foreach ($data['rows'] as $row) {
                $contract = Contract::create([
                    'name' => $row['name'],
                    'vendor_id' => $row['vendor_id'] ?? null,
                    'category_id' => $row['category_id'] ?? null,
                    'using_unit' => $row['using_unit'] ?? null,
                    'owner_id' => $row['owner_id'] ?? null,
                    'manager_id' => $row['manager_id'] ?? null,
                    'currency' => $row['currency'] ?? 'VND',
                    'unit_price' => $row['unit_price'] ?? null,
                    'monthly_cost' => $row['monthly_cost'] ?? null,
                    'annual_cost' => $row['annual_cost'] ?? null,
                    'lifecycle_cost' => $row['lifecycle_cost'] ?? null,
                    'payment_status' => $row['payment_status'] ?? ContractPaymentStatus::Unpaid->value,
                    'signed_date' => $row['signed_date'] ?? null,
                    'effective_date' => $row['effective_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'status' => $row['status'] ?? ContractStatus::Active->value,
                ]);

                ContractActivityLogger::created($contract, $account);
                $created++;
            }
        });

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            "Nhập {$created} hợp đồng từ Excel",
        );

        return back()->with('success', "Đã nhập {$created} hợp đồng từ file.");
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
