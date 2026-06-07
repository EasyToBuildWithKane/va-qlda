<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\ApproveAiPaymentRequestRequest;
use App\Http\Requests\AiAccount\CreateAiPaymentRequestRequest;
use App\Http\Requests\AiAccount\MarkPaidAiPaymentRequestRequest;
use App\Http\Requests\AiAccount\RejectAiPaymentRequestRequest;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiPaymentRequestStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AiPaymentRequestController extends Controller
{
    public function __construct(
        private readonly AiPurchaseProposalPresenter $proposalPresenter,
    ) {}

    public function store(CreateAiPaymentRequestRequest $request, AiPurchaseProposal $proposal): JsonResponse
    {
        if ($proposal->paymentRequest !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Phiếu đề xuất này đã có đề nghị thanh toán.',
            ], 422);
        }

        $amount = $request->validated('amount') ?? $proposal->cost_amount;

        $pr = DB::transaction(function () use ($proposal, $amount, $request) {
            return AiPaymentRequest::create([
                'ai_purchase_proposal_id' => $proposal->id,
                'amount' => $amount,
                'status' => AiPaymentRequestStatus::Pending,
                'created_by' => $request->user()->id,
            ]);
        });

        $proposal->loadMissing(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->proposalPresenter->row($proposal->fresh(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']), $request->user())],
            'message' => "Đã tạo đề nghị thanh toán {$pr->payment_request_code}.",
        ], 201);
    }

    public function approve(ApproveAiPaymentRequestRequest $request, AiPaymentRequest $paymentRequest): JsonResponse
    {
        DB::transaction(function () use ($paymentRequest, $request) {
            $paymentRequest->update([
                'status' => AiPaymentRequestStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        $proposal = $paymentRequest->proposal->fresh(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->proposalPresenter->row($proposal, $request->user())],
            'message' => 'Đã duyệt đề nghị thanh toán. Có thể tiến hành lập tài khoản AI.',
        ]);
    }

    public function reject(RejectAiPaymentRequestRequest $request, AiPaymentRequest $paymentRequest): JsonResponse
    {
        DB::transaction(function () use ($paymentRequest, $request) {
            $paymentRequest->update([
                'status' => AiPaymentRequestStatus::Rejected,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('rejection_reason'),
            ]);
        });

        $proposal = $paymentRequest->proposal->fresh(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->proposalPresenter->row($proposal, $request->user())],
            'message' => 'Đã từ chối đề nghị thanh toán.',
        ]);
    }

    public function markPaid(MarkPaidAiPaymentRequestRequest $request, AiPaymentRequest $paymentRequest): JsonResponse
    {
        $paidAt = $request->validated('paid_at') ? \Carbon\Carbon::parse($request->validated('paid_at')) : now();
        $actualAmount = $request->validated('actual_amount');

        DB::transaction(function () use ($paymentRequest, $paidAt, $actualAmount) {
            $data = [
                'status' => AiPaymentRequestStatus::Paid,
                'paid_at' => $paidAt,
            ];
            if ($actualAmount !== null) {
                $data['amount'] = $actualAmount;
            }
            $paymentRequest->update($data);
        });

        $proposal = $paymentRequest->proposal->fresh(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']);

        return response()->json([
            'success' => true,
            'data' => ['proposal' => $this->proposalPresenter->row($proposal, $request->user())],
            'message' => 'Đã ghi nhận thanh toán.',
        ]);
    }
}
