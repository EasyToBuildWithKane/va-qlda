<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\ApproveAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\RejectAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\StoreAiPurchaseProposalRequest;
use App\Http\Requests\AiAccount\UpdateAiPurchaseProposalNotesRequest;
use App\Http\Requests\AiAccount\UpdateAiPurchaseProposalRequest;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiPurchaseProposalDocumentService;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Services\AiAccount\AiWorkflowMetricsBuilder;
use App\Support\AiPurchaseProposalRegistrationEmails;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPaymentRequestStatus;
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
        private readonly AiWorkflowMetricsBuilder $workflowMetrics,
    ) {}

    public function awaitingAccount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        $proposals = AiPurchaseProposal::query()
            ->withCount('linkedAccounts')
            ->with('paymentRequest')
            ->whereIn('status', [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Purchased,
                AiPurchaseProposalStatus::Active,
            ])
            ->whereHas('paymentRequest', fn ($q) => $q->whereIn('status', [
                AiPaymentRequestStatus::Approved->value,
                AiPaymentRequestStatus::Paid->value,
            ]))
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (AiPurchaseProposal $p) => $p->hasRemainingAccountSlots());

        return response()->json([
            'success' => true,
            'data' => [
                'proposals' => $proposals
                    ->map(fn (AiPurchaseProposal $p) => $this->presenter->awaitingAccountOption($p))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        $query = AiPurchaseProposal::with(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer'])
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('proposal_code', 'like', "%{$search}%")
                    ->orWhere('tool_name', 'like', "%{$search}%")
                    ->orWhere('proposer_name', 'like', "%{$search}%")
                    ->orWhere('proposer_department', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('subject_about', 'like', "%{$search}%")
                    ->orWhere('proposal_content', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('proposal_type')) {
            $query->where('proposal_type', $type);
        }

        if ($dept = trim((string) $request->query('department', ''))) {
            $query->where('proposer_department', 'like', "%{$dept}%");
        }

        if ($group = $request->query('group_function')) {
            $enum = AiAccountGroupFunction::tryFrom((string) $group);
            if ($enum !== null) {
                $query->where('group_function', $enum);
            }
        }

        if ($purchaseType = $request->query('purchase_type')) {
            $enum = AiPurchaseType::tryFrom((string) $purchaseType);
            if ($enum !== null) {
                $query->where('purchase_type', $enum);
            }
        }

        if ($costUnit = $request->query('cost_unit')) {
            $enum = AiAccountCostUnit::tryFrom((string) $costUnit);
            if ($enum !== null) {
                $query->where('cost_unit', $enum);
            }
        }

        if ($vendor = trim((string) $request->query('vendor', ''))) {
            $query->where('vendor_name', $vendor);
        }

        if ($tool = trim((string) $request->query('tool_name', ''))) {
            $query->where('tool_name', $tool);
        }

        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $proposals = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'proposals' => $this->presenter->list($proposals, $request->user()),
                'counts' => $this->presenter->aggregateCounts(),
                'filtered_counts' => $this->presenter->counts($proposals),
                'workflow_metrics' => $this->workflowMetrics->build(),
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
        $staff = (int) ($validated['staff_count'] ?? 1);
        $registrationEmails = AiPurchaseProposalRegistrationEmails::normalize(
            $validated['registration_emails'] ?? null,
            $staff,
            $validated['registration_email'] ?? null,
        );

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
            'registration_emails' => $registrationEmails,
            'registration_email' => AiPurchaseProposalRegistrationEmails::firstFilled($registrationEmails),
            'planned_use_date' => $validated['planned_use_date'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }

    public function destroy(AiPurchaseProposal $proposal): JsonResponse
    {
        $this->authorize('delete', $proposal);

        $label = $proposal->proposal_code ?? $proposal->tool_name;

        foreach ($proposal->linkedAccounts()->get() as $account) {
            $account->delete();
        }

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
            'message' => 'Đã duyệt phiếu — chi phí đã được tính vào báo cáo. Vào Tài khoản AI → Thêm tài khoản để chọn mã phiếu và hoàn tất đăng ký.',
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

    /** @deprecated Trạng thái purchased/active giờ quản lý qua vòng đời tài khoản AI. */
    public function markPurchased(Request $request, AiPurchaseProposal $proposal): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tính năng này đã được thay thế. Vui lòng lập tài khoản AI từ màn Tài khoản AI.',
        ], 410);
    }

    /** @deprecated Trạng thái purchased/active giờ quản lý qua vòng đời tài khoản AI. */
    public function markActive(Request $request, AiPurchaseProposal $proposal): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tính năng này đã được thay thế. Vui lòng lập tài khoản AI từ màn Tài khoản AI.',
        ], 410);
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

    public function exportPaymentRequestPdf(AiPurchaseProposal $proposal)
    {
        $this->authorize('viewAny', AiPurchaseProposal::class);

        return $this->documentService->downloadPaymentRequestPdf($proposal);
    }
}
