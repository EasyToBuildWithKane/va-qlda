<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\ApproveAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\PreviewAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\RejectAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\StoreAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\UpdateAiPurchaseProposalNotesRequest;
use App\Http\Requests\AiAccount\UpdateAiPurchaseProposalRequest;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiPurchaseProposalDocumentService;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\AiPurchaseType;
use App\Support\Enums\ProposalType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPurchaseProposalController extends Controller
{
    public function __construct(
        private readonly AiPurchaseProposalPresenter $presenter,
        private readonly AiPurchaseProposalDocumentService $documentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        $query = AiPurchaseProposal::with(['creator.employee', 'reviewer.employee'])
            ->orderByDesc('created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('proposal_code', 'like', "%{$search}%")
                    ->orWhere('tool_name', 'like', "%{$search}%")
                    ->orWhere('proposer_name', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('subject_about', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('proposal_type')) {
            $query->where('proposal_type', $type);
        }

        if ($dept = $request->query('department')) {
            $query->where('proposer_department', 'like', "%{$dept}%");
        }

        $proposals = $query->get();
        $allForCounts = AiPurchaseProposal::query()->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'proposals' => $this->presenter->list($proposals, $request->user()),
                'counts' => $this->presenter->counts($allForCounts),
            ],
        ]);
    }

    public function store(StoreAiPurchaseProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $proposal = AiPurchaseProposal::create([
            ...$this->proposalAttributesFromValidated($validated),
            'status' => AiPurchaseProposalStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal, $request->user())],
            'message' => 'Đã gửi phiếu đề xuất. Chờ quản trị duyệt.',
        ], 201);
    }

    public function update(UpdateAiPurchaseProposalRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $proposal->update($this->proposalAttributesFromValidated($request->validated()));

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
            'message' => 'Đã cập nhật phiếu đề xuất.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function proposalAttributesFromValidated(array $validated): array
    {
        return [
            'proposal_type' => isset($validated['proposal_type'])
                ? ProposalType::from($validated['proposal_type'])
                : ProposalType::AiAccount,
            'subject_about' => $validated['subject_about'],
            'send_to' => $validated['send_to'] ?? config('ai_accounts.proposal.send_to_default'),
            'tool_name' => $validated['tool_name'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'vendor_website' => $validated['vendor_website'] ?? null,
            'group_function' => AiAccountGroupFunction::from($validated['group_function']),
            'license_type' => $validated['license_type'],
            'cost_amount' => (int) $validated['cost_amount'],
            'cost_unit' => AiAccountCostUnit::from($validated['cost_unit']),
            'seats' => $validated['seats'] ?? null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'justification' => $validated['justification'] ?? $validated['proposal_content'],
            'proposal_content' => $validated['proposal_content'],
            'description' => $validated['description'] ?? null,
            'reason_for_proposal' => $validated['reason_for_proposal'] ?? null,
            'expected_benefit' => $validated['expected_benefit'] ?? null,
            'objectives' => $validated['objectives'] ?? null,
            'proposer_name' => $validated['proposer_name'],
            'proposer_position' => $validated['proposer_position'] ?? null,
            'proposer_department' => $validated['proposer_department'] ?? null,
            'staff_count' => $validated['staff_count'] ?? 1,
            'users_list' => $validated['users_list'] ?? [],
            'department_using' => $validated['department_using'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? $validated['proposer_name'],
            'recipient_position' => $validated['recipient_position'] ?? $validated['proposer_position'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'purchase_type' => AiPurchaseType::from($validated['purchase_type']),
            'registration_email' => $validated['registration_email'] ?? null,
            'planned_use_date' => $validated['planned_use_date'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }

    public function preview(PreviewAiPurchaseProposalRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'html' => $this->documentService->renderPreviewHtml($request->validated()),
            ],
        ]);
    }

    public function destroy(AiPurchaseProposal $proposal): JsonResponse
    {
        $this->authorize('delete', $proposal);

        $label = $proposal->proposal_code ?? $proposal->tool_name;
        $proposal->delete();

        return response()->json([
            'success' => true,
            'message' => "Đã xoá phiếu {$label}.",
        ]);
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
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
            'message' => 'Đã duyệt phiếu đề xuất.',
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
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
            'message' => 'Đã từ chối phiếu đề xuất.',
        ]);
    }

    public function markPurchased(Request $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $this->authorize('manage', $proposal);

        $proposal->update(['status' => AiPurchaseProposalStatus::Purchased]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
            'message' => 'Đã đánh dấu đã mua.',
        ]);
    }

    public function markActive(Request $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $this->authorize('manage', $proposal);

        $proposal->update(['status' => AiPurchaseProposalStatus::Active]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
            'message' => 'Đã đánh dấu đang sử dụng.',
        ]);
    }

    public function updateNotes(UpdateAiPurchaseProposalNotesRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        $proposal->update([
            'review_notes' => $request->validated('review_notes'),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->presenter->row($proposal->fresh(), $request->user())],
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
