<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\ApproveAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\RejectAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\StoreAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\UpdateAiPurchaseProposalNotesRequest;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiPurchaseProposalDocumentService;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\AiPurchaseType;
use Illuminate\Http\JsonResponse;

class AiPurchaseProposalController extends Controller
{
    public function __construct(
        private readonly AiPurchaseProposalPresenter $presenter,
        private readonly AiPurchaseProposalDocumentService $documentService,
    ) {}

    public function store(StoreAiPurchaseProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $proposal = AiPurchaseProposal::create([
            'subject_about' => $validated['subject_about'],
            'send_to' => $validated['send_to'] ?? config('ai_accounts.proposal.send_to_default'),
            'tool_name' => $validated['tool_name'],
            'group_function' => AiAccountGroupFunction::from($validated['group_function']),
            'license_type' => $validated['license_type'],
            'cost_amount' => (int) $validated['cost_amount'],
            'cost_unit' => AiAccountCostUnit::from($validated['cost_unit']),
            'seats' => $validated['seats'] ?? null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'justification' => $validated['justification'] ?? $validated['proposal_content'],
            'proposal_content' => $validated['proposal_content'],
            'objectives' => $validated['objectives'] ?? null,
            'proposer_name' => $validated['proposer_name'],
            'proposer_position' => $validated['proposer_position'] ?? null,
            'proposer_department' => $validated['proposer_department'] ?? null,
            'staff_count' => $validated['staff_count'] ?? 1,
            'recipient_name' => $validated['recipient_name'] ?? $validated['proposer_name'],
            'recipient_position' => $validated['recipient_position'] ?? $validated['proposer_position'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'purchase_type' => AiPurchaseType::from($validated['purchase_type']),
            'registration_email' => $validated['registration_email'] ?? null,
            'planned_use_date' => $validated['planned_use_date'] ?? null,
            'status' => AiPurchaseProposalStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal)],
            'message' => 'Đã gửi đề xuất mua. Chờ quản trị duyệt.',
        ], 201);
    }

    public function approve(ApproveAiPurchaseProposalRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $notes = $request->validated('review_notes');

        $proposal->update([
            'status' => AiPurchaseProposalStatus::Approved,
            'rejection_reason' => null,
            'review_notes' => $notes ? trim((string) $notes) : $proposal->review_notes,
            'reviewed_by' => $request->user()->id,
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

    public function updateNotes(UpdateAiPurchaseProposalNotesRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $proposal->update([
            'review_notes' => $request->validated('review_notes'),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh())],
            'message' => 'Đã lưu ghi chú.',
        ]);
    }

    public function exportDocx(AiPurchaseProposal $proposal)
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        return $this->documentService->downloadDocx($proposal);
    }

    public function exportPdf(AiPurchaseProposal $proposal)
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        return $this->documentService->downloadPdf($proposal);
    }
}
