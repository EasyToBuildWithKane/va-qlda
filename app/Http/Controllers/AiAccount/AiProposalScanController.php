<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\ConfirmProposalScanRequest;
use App\Http\Requests\AiAccount\StoreProposalScanRequest;
use App\Http\Requests\AiAccount\UpdateProposalScanRequest;
use App\Models\AiProposalScan;
use App\Models\AiProposalScanSignature;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\ProposalOcrClient;
use App\Services\AiAccount\ProposalScanRecorder;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiProposalScanStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\AiPurchaseType;
use App\Support\Enums\ProposalType;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiProposalScanController extends Controller
{
    public function __construct(
        private readonly ProposalOcrClient $ocrClient,
        private readonly ProposalScanRecorder $recorder,
    ) {}

    public function store(StoreProposalScanRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $user = $request->user();

        try {
            $result = $this->ocrClient->extract($file);
        } catch (RuntimeException $e) {
            $scan = $this->recorder->markFailed($file, $e->getMessage(), $user);

            return response()->json([
                'success' => false,
                'data' => ['scan' => $this->serializeScan($scan)],
                'message' => $e->getMessage(),
            ], 422);
        }

        $scan = $this->recorder->record($file, $result, $user);

        SecurityAuditLogger::aiProposal($user, 'scan_extracted', null, [
            'scan_id' => $scan->id,
            'fields' => count($scan->extracted_fields ?? []),
            'signatures' => $scan->signatures->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['scan' => $this->serializeScan($scan)],
            'message' => 'Đã trích xuất dữ liệu từ phiếu. Vui lòng kiểm tra trước khi lưu.',
        ], 201);
    }

    public function show(AiProposalScan $scan): JsonResponse
    {
        $this->authorize('view', $scan);

        return response()->json([
            'success' => true,
            'data' => ['scan' => $this->serializeScan($scan->load(['signatures', 'proposal']))],
        ]);
    }

    public function update(UpdateProposalScanRequest $request, AiProposalScan $scan): JsonResponse
    {
        $values = $request->cleanFieldValues();
        $fields = $scan->extracted_fields ?? [];

        foreach ($values as $key => $value) {
            $original = $fields[$key]['value'] ?? null;
            $fields[$key] = [
                'value' => $value,
                // Người dùng đã xác nhận bằng tay → tin cậy tuyệt đối.
                'confidence' => $original === $value ? ($fields[$key]['confidence'] ?? 1.0) : 1.0,
            ];
        }

        foreach (array_keys($fields) as $key) {
            if (! array_key_exists($key, $values)) {
                unset($fields[$key]);
            }
        }

        $scan->update(['extracted_fields' => $fields]);

        return response()->json([
            'success' => true,
            'data' => ['scan' => $this->serializeScan($scan->fresh()->load('signatures'))],
            'message' => 'Đã cập nhật dữ liệu trích xuất.',
        ]);
    }

    public function confirm(ConfirmProposalScanRequest $request, AiProposalScan $scan): JsonResponse
    {
        $validated = $request->validated();
        $fields = $scan->extracted_fields ?? [];

        $proposal = DB::transaction(function () use ($validated, $fields, $scan, $request) {
            $proposal = AiPurchaseProposal::create([
                'proposal_type' => ProposalType::Other,
                'subject_about' => $validated['subject_about'],
                'send_to' => $validated['send_to'] ?? null,
                'tool_name' => Str::limit($validated['subject_about'], 250, '…'),
                'group_function' => AiAccountGroupFunction::Other,
                'license_type' => 'Khác',
                'cost_amount' => (int) $validated['cost_amount'],
                'cost_unit' => AiAccountCostUnit::tryFrom((string) ($validated['cost_unit'] ?? '')) ?? AiAccountCostUnit::OneTime,
                'quantity' => (int) ($validated['quantity'] ?? 1),
                'proposal_content' => $validated['proposal_content'],
                'justification' => $validated['justification'] ?? $validated['proposal_content'],
                'proposer_name' => $validated['proposer_name'],
                'proposer_position' => $validated['proposer_position'] ?? null,
                'proposer_department' => $validated['proposer_department'] ?? null,
                'purchase_type' => AiPurchaseType::New,
                'description' => $this->scanProvenanceNote($scan, $fields, $validated['notes'] ?? null),
                'attachment_paths' => [$scan->original_path],
                'status' => AiPurchaseProposalStatus::Pending,
                'created_by' => $request->user()->id,
            ]);

            $scan->update([
                'ai_purchase_proposal_id' => $proposal->id,
                'status' => AiProposalScanStatus::Confirmed,
            ]);

            return $proposal;
        });

        SecurityAuditLogger::aiProposal($request->user(), 'created_from_scan', null, [
            'proposal_id' => $proposal->id,
            'scan_id' => $scan->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'scan' => $this->serializeScan($scan->fresh()->load(['signatures', 'proposal'])),
                'proposal_id' => $proposal->id,
                'proposal_code' => $proposal->proposal_code,
            ],
            'message' => "Đã lưu Phiếu Đề Xuất {$proposal->proposal_code} từ bản quét. Phiếu đang chờ duyệt.",
        ]);
    }

    public function file(AiProposalScan $scan): StreamedResponse
    {
        $this->authorize('view', $scan);
        abort_unless($scan->fileExists(), 404);

        return Storage::disk('public')->response($scan->original_path, $scan->original_name);
    }

    public function signatureFile(AiProposalScan $scan, AiProposalScanSignature $signature): StreamedResponse
    {
        $this->authorize('view', $scan);
        abort_unless($signature->ai_proposal_scan_id === $scan->id, 404);
        abort_unless($signature->imageExists(), 404);

        return Storage::disk('public')->response(
            $signature->image_path,
            "chu-ky-{$signature->role->value}.png",
        );
    }

    /**
     * @param  array<string, array{value?: string, confidence?: float}>  $fields
     */
    private function scanProvenanceNote(AiProposalScan $scan, array $fields, ?string $notes): string
    {
        $parts = ['Số hóa từ phiếu giấy (OCR).'];

        if ($code = $fields['proposal_code']['value'] ?? null) {
            $parts[] = "Số phiếu gốc: {$code}.";
        }
        if ($date = $fields['proposal_date']['value'] ?? null) {
            $parts[] = "Ngày lập: {$date}.";
        }
        if ($notes !== null && trim($notes) !== '') {
            $parts[] = 'Ghi chú: '.trim($notes);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScan(AiProposalScan $scan): array
    {
        $scan->loadMissing('signatures');

        return [
            'id' => $scan->id,
            'status' => [
                'value' => $scan->status->value,
                'label' => $scan->status->labelVi(),
                'color' => $scan->status->badgeColor(),
            ],
            'original_name' => $scan->original_name,
            'mime_type' => $scan->mime_type,
            'size' => $scan->size,
            'file_url' => $scan->fileUrl(),
            'pages' => $scan->pages,
            'duration_ms' => $scan->duration_ms,
            'error_message' => $scan->error_message,
            'raw_text' => $scan->raw_text,
            'fields' => (object) ($scan->extracted_fields ?? []),
            'signatures' => $scan->signatures->map(fn (AiProposalScanSignature $sig) => [
                'id' => $sig->id,
                'role' => [
                    'value' => $sig->role->value,
                    'label' => $sig->role->labelVi(),
                ],
                'signed' => $sig->signed,
                'signer_name' => $sig->signer_name,
                'confidence' => $sig->confidence,
                'page' => $sig->page,
                'image_url' => $sig->imageUrl(),
            ])->values()->all(),
            'proposal' => $scan->relationLoaded('proposal') && $scan->proposal
                ? [
                    'id' => $scan->proposal->id,
                    'code' => $scan->proposal->proposal_code,
                ]
                : null,
            'created_at' => $scan->created_at?->toIso8601String(),
        ];
    }
}
