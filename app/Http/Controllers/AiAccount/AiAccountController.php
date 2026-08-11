<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\RenewAiAccountRequest;
use App\Http\Requests\AiAccount\StoreAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountStatusRequest;
use App\Models\AiAccount;
use App\Services\AiAccount\AiAccountCostSummaryBuilder;
use App\Services\AiAccount\AiAccountDocumentService;
use App\Services\AiAccount\AiAccountGrouper;
use App\Services\AiAccount\AiAccountReminderService;
use App\Services\AiAccount\AiAccountStatusSync;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use App\Support\SecurityAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAccountController extends Controller
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
        private readonly AiAccountCostSummaryBuilder $costSummaryBuilder,
        private readonly AiAccountStatusSync $statusSync,
        private readonly AiAccountReminderService $reminderService,
        private readonly AiAccountDocumentService $documentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $accounts = $this->loadAndSyncAccounts();
        $search = $request->query('search');
        $payload = $this->grouper->grouped($accounts, is_string($search) ? $search : null, $request->user());
        $summary = $this->costSummaryBuilder->build($accounts);

        return response()->json([
            'success' => true,
            'data' => [
                ...$payload,
                'summary_cards' => $summary['cards'],
            ],
            'meta' => [
                'options' => [
                    'group_function' => AiAccountGroupFunction::options(),
                    'cost_unit' => AiAccountCostUnit::options(),
                    'status' => AiAccountStatus::options(),
                ],
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $accounts = $this->loadAndSyncAccounts();
        $summary = $this->costSummaryBuilder->build($accounts);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function show(Request $request, AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('view', $aiAccount);
        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
        ]);
    }

    public function store(StoreAiAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = DB::transaction(function () use ($request, $validated) {
            $account = AiAccount::query()->create([
                'tool_name' => $validated['tool_name'],
                'group_function' => $validated['group_function'],
                'email_registered' => $validated['email_registered'],
                'login_password' => $request->user()->isAdminTier()
                    ? ($validated['password'] ?? null)
                    : null,
                'purchase_date' => $validated['purchase_date'],
                'expiry_date' => $validated['expiry_date'],
                'cost_amount' => (int) $validated['cost_amount'],
                'cost_unit' => $validated['cost_unit'],
                'notify_before_days' => $validated['notify_before_days']
                    ?? (int) config('ai_accounts.defaults.notify_before_days', 14),
                'proposal_sent_at' => $validated['proposal_sent_at'] ?? null,
                'payment_request_sent_at' => $validated['payment_request_sent_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => AiAccountStatus::Active,
                'proposal_document_paths' => [],
                'payment_request_document_paths' => [],
            ]);

            $proposalDocs = $this->documentService->storeUploads(
                $account,
                AiAccountDocumentService::KIND_PROPOSAL,
                $request->file('proposal_documents'),
            );
            $paymentDocs = $this->documentService->storeUploads(
                $account,
                AiAccountDocumentService::KIND_PAYMENT_REQUEST,
                $request->file('payment_request_documents'),
            );

            $account->update([
                'proposal_document_paths' => $proposalDocs,
                'payment_request_document_paths' => $paymentDocs,
            ]);

            $this->statusSync->syncAndSave($account->fresh());

            return $account->fresh();
        });

        SecurityAuditLogger::aiAccount($request->user(), 'created', null, [
            'ai_account_id' => $account->id,
            'tool' => $account->tool_name,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($account, $request->user())],
            'message' => 'Đã tạo tài khoản AI.',
        ], 201);
    }

    public function update(UpdateAiAccountRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $aiAccount, $validated) {
            $data = [
                'tool_name' => $validated['tool_name'],
                'group_function' => $validated['group_function'],
                'email_registered' => $validated['email_registered'],
                'purchase_date' => $validated['purchase_date'],
                'expiry_date' => $validated['expiry_date'],
                'cost_amount' => (int) $validated['cost_amount'],
                'cost_unit' => $validated['cost_unit'],
                'notify_before_days' => $validated['notify_before_days'] ?? $aiAccount->notify_before_days,
                'proposal_sent_at' => $validated['proposal_sent_at'] ?? null,
                'payment_request_sent_at' => $validated['payment_request_sent_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            if ($request->user()->isAdminTier() && ! empty($validated['password'])) {
                $data['login_password'] = $validated['password'];
            }

            if ($request->user()->can('updateStatus', $aiAccount) && ! empty($validated['status'] ?? null)) {
                $status = AiAccountStatus::from($validated['status']);
                $data['status'] = $status;
                if ($status === AiAccountStatus::Expired && (bool) ($validated['sync_expiry_on_expire'] ?? true)) {
                    $data['expiry_date'] = now()->startOfDay();
                }
            }

            $aiAccount->update($data);

            $this->mergeDocuments(
                $aiAccount,
                AiAccountDocumentService::KIND_PROPOSAL,
                $request->file('proposal_documents'),
                (bool) ($validated['replace_proposal_documents'] ?? false),
            );
            $this->mergeDocuments(
                $aiAccount,
                AiAccountDocumentService::KIND_PAYMENT_REQUEST,
                $request->file('payment_request_documents'),
                (bool) ($validated['replace_payment_request_documents'] ?? false),
            );

            $fresh = $aiAccount->fresh();
            if ($fresh
                && $fresh->status !== AiAccountStatus::Cancelled
                && $fresh->status !== AiAccountStatus::Expired
            ) {
                $this->statusSync->syncAndSave($fresh);
            }
        });

        SecurityAuditLogger::aiAccount($request->user(), 'updated', null, [
            'ai_account_id' => $aiAccount->id,
            'tool' => $aiAccount->tool_name,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã lưu thành công.',
        ]);
    }

    public function updateStatus(UpdateAiAccountStatusRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();
        $status = AiAccountStatus::from($validated['status']);
        $data = ['status' => $status];

        if (! empty($validated['expiry_date'] ?? null)) {
            $data['expiry_date'] = Carbon::parse($validated['expiry_date'])->startOfDay();
        } elseif ((bool) ($validated['sync_expiry_on_expire'] ?? true) && $status === AiAccountStatus::Expired) {
            $data['expiry_date'] = now()->startOfDay();
        }

        $aiAccount->update($data);

        if ($status !== AiAccountStatus::Cancelled && $status !== AiAccountStatus::Expired) {
            $this->statusSync->syncAndSave($aiAccount->fresh());
        }

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã cập nhật trạng thái tài khoản.',
        ]);
    }

    public function destroy(AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('delete', $aiAccount);
        $name = $aiAccount->tool_name;

        $this->documentService->deleteFiles($aiAccount->proposal_document_paths ?? []);
        $this->documentService->deleteFiles($aiAccount->payment_request_document_paths ?? []);
        $aiAccount->delete();

        SecurityAuditLogger::aiAccount(request()->user(), 'deleted', null, [
            'ai_account_id' => $aiAccount->id,
            'tool' => $name,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Đã xoá {$name}.",
        ]);
    }

    public function renew(RenewAiAccountRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $months = (int) $validated['period_months'];

        $aiAccount->update([
            'purchase_date' => $start,
            'expiry_date' => $start->copy()->addMonths($months),
            'cost_amount' => (int) $validated['new_cost'],
        ]);

        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã gia hạn tài khoản.',
        ]);
    }

    public function triggerReminder(Request $request): JsonResponse
    {
        $this->authorize('triggerReminder', AiAccount::class);

        $count = $this->reminderService->sendDueReminders();

        return response()->json([
            'success' => true,
            'data' => ['sent' => $count],
            'message' => $count > 0
                ? "Đã gửi {$count} nhắc nhở hết hạn."
                : 'Không có tài khoản nào cần nhắc hôm nay.',
        ]);
    }

    public function documentFile(
        Request $request,
        AiAccount $aiAccount,
        string $kind,
        int $index,
    ): StreamedResponse {
        $this->authorize('view', $aiAccount);

        $normalized = $kind === 'payment-request' ? 'payment_request' : 'proposal';
        abort_unless(in_array($normalized, ['proposal', 'payment_request'], true), 404);

        $docs = $normalized === 'payment_request'
            ? ($aiAccount->payment_request_document_paths ?? [])
            : ($aiAccount->proposal_document_paths ?? []);

        abort_unless(isset($docs[$index]) && is_array($docs[$index]), 404);

        $path = $docs[$index]['path'] ?? null;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('public')->exists($path), 404);

        $name = is_string($docs[$index]['original_name'] ?? null)
            ? $docs[$index]['original_name']
            : basename($path);
        $mime = is_string($docs[$index]['mime_type'] ?? null)
            ? $docs[$index]['mime_type']
            : 'application/octet-stream';

        return Storage::disk('public')->response($path, $name, ['Content-Type' => $mime]);
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>|null  $files
     */
    private function mergeDocuments(
        AiAccount $account,
        string $kind,
        ?array $files,
        bool $replace,
    ): void {
        $attr = $kind === AiAccountDocumentService::KIND_PAYMENT_REQUEST
            ? 'payment_request_document_paths'
            : 'proposal_document_paths';

        $existing = $account->{$attr} ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }

        $uploaded = $this->documentService->storeUploads($account, $kind, $files);
        if ($uploaded === [] && ! $replace) {
            return;
        }

        if ($replace) {
            $this->documentService->deleteFiles($existing);
            $account->update([$attr => $uploaded]);

            return;
        }

        $account->update([$attr => array_values(array_merge($existing, $uploaded))]);
    }

    private function loadAndSyncAccounts()
    {
        $accounts = AiAccount::query()
            ->orderBy('tool_name')
            ->get();
        $this->statusSync->syncCollection($accounts);

        return $accounts->map(fn (AiAccount $a) => $a->fresh())->filter()->values();
    }

    private function accountRow(?AiAccount $account, ?\App\Models\SystemAccount $viewer = null): ?array
    {
        if (! $account) {
            return null;
        }

        return $this->grouper->accountPayload($account, $viewer);
    }
}
