<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\UpdateCongngheSoftwareProposalRequest;
use App\Http\Resources\CongngheSoftwareProposalResource;
use App\Mail\CongngheSoftwareProposalRejectedMail;
use App\Models\CongngheSoftwareProposal;
use App\Support\Enums\CongngheSoftwareProposalStatus;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CongngheSoftwareProposalManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CongngheSoftwareProposal::class);

        $query = CongngheSoftwareProposal::query()->withCount('attachments');

        $group = $request->query('group', 'department');
        if ($group === 'department') {
            $query->orderBy('department')->orderByDesc('created_at');
        } else {
            $query->latest();
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($department = trim((string) $request->query('department', ''))) {
            $query->where('department', $department);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhere('submitter_name', 'like', "%{$search}%")
                    ->orWhere('submitter_email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->query('email_pending') === '1') {
            $query->whereNull('email_sent_at');
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);

        return Inertia::render('Congnghe/Proposals/Index', [
            'proposals' => CongngheSoftwareProposalResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) $request->only(['status', 'q', 'email_pending', 'department', 'group', 'per_page']),
            'summary' => [
                'total' => CongngheSoftwareProposal::count(),
                'new' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::New)->count(),
                'triaged' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::Triaged)->count(),
                'in_progress' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::InProgress)->count(),
                'done' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::Done)->count(),
                'rejected' => CongngheSoftwareProposal::where('status', CongngheSoftwareProposalStatus::Rejected)->count(),
                'email_pending' => CongngheSoftwareProposal::whereNull('email_sent_at')->count(),
            ],
            'options' => [
                'statuses' => CongngheSoftwareProposalStatus::options(),
                'departments' => CongngheSoftwareProposal::query()
                    ->select('department')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department')
                    ->values()
                    ->all(),
            ],
            'can' => [
                'manage' => $request->user()->can('viewAny', CongngheSoftwareProposal::class),
            ],
        ]);
    }

    public function show(CongngheSoftwareProposal $proposal): Response
    {
        $this->authorize('view', $proposal);

        $proposal->load('attachments');

        return Inertia::render('Congnghe/Proposals/Show', [
            'proposal' => (new CongngheSoftwareProposalResource($proposal))->resolve(),
            'options' => [
                'statuses' => CongngheSoftwareProposalStatus::options(),
            ],
        ]);
    }

    public function update(
        UpdateCongngheSoftwareProposalRequest $request,
        CongngheSoftwareProposal $proposal,
    ): RedirectResponse {
        $validated = $request->validated();
        $newStatus = CongngheSoftwareProposalStatus::from($validated['status']);
        $previousStatus = $proposal->status;

        $attributes = ['status' => $newStatus];

        if ($newStatus === CongngheSoftwareProposalStatus::Rejected) {
            $attributes['rejection_reason'] = trim((string) $validated['rejection_reason']);
        } else {
            $attributes['rejection_reason'] = null;
            $attributes['rejection_email_sent_at'] = null;
            $attributes['rejection_email_error'] = null;
        }

        $proposal->update($attributes);
        $proposal->refresh();

        SecurityAuditLogger::congngheProposal($request->user(), 'status_changed', $proposal->id, [
            'reference_code' => $proposal->reference_code,
            'status' => $proposal->status->value,
            'from' => $previousStatus->value,
        ]);

        if ($newStatus === CongngheSoftwareProposalStatus::Rejected) {
            return $this->finishRejectionUpdate($proposal, $attributes['rejection_reason']);
        }

        return back()->with('success', 'Đã cập nhật trạng thái đề xuất.');
    }

    private function finishRejectionUpdate(CongngheSoftwareProposal $proposal, string $rejectionReason): RedirectResponse
    {
        $email = trim($proposal->submitter_email);

        if ($email === '') {
            $proposal->forceFill([
                'rejection_email_error' => 'Không có email người gửi.',
            ])->saveQuietly();

            return back()->with(
                'warning',
                'Đã từ chối đề xuất nhưng không gửi được email vì thiếu địa chỉ người gửi.',
            );
        }

        try {
            Mail::to($email)->send(new CongngheSoftwareProposalRejectedMail($proposal, $rejectionReason));
            $proposal->forceFill([
                'rejection_email_sent_at' => now(),
                'rejection_email_error' => null,
            ])->saveQuietly();

            return back()->with('success', 'Đã từ chối đề xuất và gửi email thông báo tới người gửi.');
        } catch (\Throwable $e) {
            report($e);
            $proposal->forceFill([
                'rejection_email_error' => mb_substr($e->getMessage(), 0, 500),
            ])->saveQuietly();

            return back()->with(
                'warning',
                'Đã từ chối đề xuất nhưng email thông báo chưa gửi được. Vui lòng liên hệ người gửi thủ công.',
            );
        }
    }
}
