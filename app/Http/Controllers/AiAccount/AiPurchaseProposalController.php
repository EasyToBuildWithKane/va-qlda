<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\RejectAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\StoreAiPurchaseProposalRequest;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Http\JsonResponse;

class AiPurchaseProposalController extends Controller
{
    public function __construct(
        private readonly AiPurchaseProposalPresenter $presenter,
    ) {}

    public function store(StoreAiPurchaseProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $proposal = AiPurchaseProposal::create([
            'tool_name' => $validated['tool_name'],
            'group_function' => AiAccountGroupFunction::from($validated['group_function']),
            'license_type' => $validated['license_type'],
            'cost_amount' => (int) $validated['cost_amount'],
            'cost_unit' => AiAccountCostUnit::from($validated['cost_unit']),
            'seats' => $validated['seats'] ?? null,
            'justification' => $validated['justification'],
            'status' => AiPurchaseProposalStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal)],
            'message' => 'Đã gửi đề xuất mua. Chờ quản trị duyệt.',
        ], 201);
    }

    public function approve(AiPurchaseProposal $proposal): JsonResponse
    {
        $this->authorize('review', $proposal);

        $proposal->update([
            'status' => AiPurchaseProposalStatus::Approved,
            'rejection_reason' => null,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh())],
            'message' => 'Đã duyệt đề xuất.',
        ]);
    }

    public function reject(RejectAiPurchaseProposalRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $proposal->update([
            'status' => AiPurchaseProposalStatus::Rejected,
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh())],
            'message' => 'Đã từ chối đề xuất.',
        ]);
    }
}
