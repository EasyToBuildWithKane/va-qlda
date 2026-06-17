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

    public function store(StoreContractRenewalRequest $request, Contract $contract): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();

        $previousExpiry = $contract->expiry_date?->toDateString();
        $previousCost = $contract->annual_cost;

        $contract->renewals()->create([
            'previous_expiry' => $previousExpiry,
            'new_expiry' => $data['new_expiry'],
            'previous_cost' => $previousCost,
            'new_cost' => $data['new_cost'] ?? null,
            'note' => $data['note'] ?? null,
            'renewed_by_id' => $account->employee_id,
        ]);

        $updates = [
            'expiry_date' => $data['new_expiry'],
            'status' => ContractStatus::Active->value,
        ];
        if (array_key_exists('new_cost', $data) && $data['new_cost'] !== null) {
            $updates['annual_cost'] = $data['new_cost'];
        }
        $contract->update($updates);

        ContractActivityLogger::renewed($contract, $previousExpiry, $data['new_expiry'], $account);

        return back()->with('success', 'Đã gia hạn hợp đồng.');
    }
}
