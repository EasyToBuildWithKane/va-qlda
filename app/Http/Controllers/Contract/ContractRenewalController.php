<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractRenewalRequest;
use App\Http\Resources\ContractListResource;
use App\Models\Contract;
use App\Support\ContractActivityLogger;
use App\Support\ContractLifecycle\ContractRenewalCalculator;
use App\Support\Enums\ContractStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContractRenewalController extends Controller
{
    public function __construct(private readonly ContractRenewalCalculator $calculator) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Contract::class);

        $milestones = $this->calculator->milestones(); // desc, vd [90,60,30,7]
        $maxWindow = $milestones === [] ? 90 : max($milestones);

        $contracts = Contract::query()
            ->with('owner', 'vendor')
            ->expiringWithin($maxWindow)
            ->orderBy('expiry_date')
            ->get();

        // Phân nhóm vào mốc nhỏ nhất phù hợp (7 trước 30 trước 60 trước 90).
        $buckets = [];
        foreach ($milestones as $m) {
            $buckets[$m] = [];
        }
        $today = Carbon::today();
        foreach ($contracts as $c) {
            $days = $this->calculator->daysUntilExpiry($c, $today);
            $matched = $days === null ? null : $this->calculator->matchedMilestone($days);
            if ($matched !== null) {
                $buckets[$matched][] = $c;
            }
        }

        $groups = collect($milestones)->map(fn (int $m) => [
            'days' => $m,
            'label' => "Trong {$m} ngày",
            'contracts' => array_values(ContractListResource::collection(collect($buckets[$m] ?? []))->resolve()),
        ])->values()->all();

        return Inertia::render('Contract/Renewals', [
            'groups' => $groups,
            'calendar' => array_values(ContractListResource::collection($contracts)->resolve()),
            'can' => [
                'manage' => request()->user()->can('create', Contract::class),
            ],
        ]);
    }

    /**
     * Gia hạn hợp đồng:
     * 1. Tạo Contract phụ lục mới (status = addendum, root_contract_id = parent).
     * 2. Ghi ContractRenewal log (audit).
     * 3. Cập nhật trạng thái hợp đồng gốc → active (nếu chưa active).
     */
    public function store(StoreContractRenewalRequest $request, Contract $contract): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();

        DB::transaction(function () use ($contract, $data, $account) {
            $previousExpiry = $contract->expiry_date?->toDateString();
            $previousCost = $contract->annual_cost;

            // Tạo hợp đồng phụ lục mới
            $addendum = Contract::create([
                'name' => $data['name'] ?? ($contract->name.' — Phụ lục'),
                'description' => $data['note'] ?? null,
                'vendor_id' => $contract->vendor_id,
                'category_id' => $contract->category_id,
                'department_id' => $contract->department_id,
                'using_unit' => $contract->using_unit,
                'owner_id' => $contract->owner_id,
                'manager_id' => $contract->manager_id,
                'currency' => $contract->currency,
                'billing_cycle' => $contract->billing_cycle?->value,
                'annual_cost' => $data['new_cost'] ?? $contract->annual_cost,
                'root_contract_id' => $contract->id,
                'effective_date' => $data['effective_date'] ?? $contract->expiry_date?->toDateString(),
                'expiry_date' => $data['new_expiry'],
                'status' => ContractStatus::Addendum->value,
                'auto_renew' => $contract->auto_renew,
                'renewal_term_months' => $contract->renewal_term_months,
                'notice_period_days' => $contract->notice_period_days,
            ]);

            // Xử lý links đính kèm cho phụ lục
            foreach ($data['links'] ?? [] as $link) {
                $link = trim((string) $link);
                if ($link === '') {
                    continue;
                }
                $addendum->attachments()->create([
                    'category' => 'contract',
                    'uploaded_by_id' => $account?->employee_id,
                    'original_name' => Str::limit(basename(str_replace('\\', '/', $link)), 200, ''),
                    'external_url' => $link,
                    'size' => 0,
                    'is_image' => false,
                    'version' => 1,
                ]);
            }

            // Ghi audit vào hợp đồng gốc
            $contract->renewals()->create([
                'previous_expiry' => $previousExpiry,
                'new_expiry' => $data['new_expiry'],
                'previous_cost' => $previousCost,
                'new_cost' => $data['new_cost'] ?? null,
                'note' => $data['note'] ?? null,
                'renewed_by_id' => $account?->employee_id,
            ]);

            ContractActivityLogger::renewed(
                $contract,
                $previousExpiry,
                $data['new_expiry'],
                $account,
            );
            ContractActivityLogger::created($addendum, $account);
        });

        return back()->with('success', 'Đã tạo phụ lục gia hạn.');
    }
}
